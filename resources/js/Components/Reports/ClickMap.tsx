import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Badge, MessageBar, MessageBarBody, Spinner, Text, Tooltip, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount, formatRate, formatText } from '@/Components/Campaigns/campaignUi';
import { EmailFrame } from '@/Components/Campaigns/EmailFrame';
import { useElementWidth } from '@/Components/Reports/ChartChrome';
import { HorizontalBars } from '@/Components/Reports/HorizontalBars';
import { reportMissingText } from '@/Components/Reports/reportUi';
import type { ReportLinkRow } from '@/Lib/types/reports';

const MAX_FRAME_WIDTH = 760;
const VIEWPORT_HEIGHT = 820;
/** Small probe viewport so scrollHeight reflects the content, not the viewport. */
const PROBE_HEIGHT = 200;

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        width: '100%',
    },
    help: {
        color: tokens.colorNeutralForeground3,
    },
    viewport: {
        maxHeight: `${VIEWPORT_HEIGHT}px`,
        overflowY: 'auto',
        overflowX: 'hidden',
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground3,
        display: 'flex',
        justifyContent: 'center',
    },
    canvas: {
        position: 'relative',
        flexShrink: 0,
    },
    overlay: {
        position: 'absolute',
        inset: 0,
        pointerEvents: 'none',
    },
    outline: {
        position: 'absolute',
        borderRadius: tokens.borderRadiusSmall,
        outline: `${tokens.strokeWidthThick} solid ${tokens.colorBrandStroke1}`,
        outlineOffset: '1px',
    },
    badge: {
        position: 'absolute',
        pointerEvents: 'auto',
        transform: 'translate(-50%, -60%)',
        boxShadow: tokens.shadow4,
        cursor: 'default',
    },
    probe: {
        position: 'absolute',
        left: '-10000px',
        top: 0,
        visibility: 'hidden',
        border: 'none',
    },
    center: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalXL,
    },
});

interface AnchorBox {
    key: string;
    link: ReportLinkRow;
    top: number;
    left: number;
    width: number;
    height: number;
}

type Measure = { status: 'pending' } | { status: 'ready'; height: number; boxes: AnchorBox[] } | { status: 'fallback' };

export interface ClickMapProps {
    html: string | null;
    links: ReportLinkRow[];
    loading?: boolean;
}

/**
 * **Carte des clics** (docs/43 §43.6.3) — the campaign HTML rendered in the
 * sandboxed `EmailFrame`, with a badge per anchor carrying `data-link-id`
 * showing its share of clicks. Anchor positions come from a hidden probe
 * iframe this component controls (same-origin srcdoc, scripts still
 * disabled); if it cannot be read, or no anchor matches, the links are shown
 * as a ranked list instead.
 */
export function ClickMap({ html, links, loading = false }: ClickMapProps) {
    const styles = useStyles();
    const t = useText();
    const rootRef = useRef<HTMLDivElement>(null);
    const probeRef = useRef<HTMLIFrameElement>(null);
    const containerWidth = useElementWidth(rootRef, MAX_FRAME_WIDTH);
    const frameWidth = Math.max(320, Math.min(MAX_FRAME_WIDTH, containerWidth - 2));
    const [measure, setMeasure] = useState<Measure>({ status: 'pending' });

    const linksById = useMemo(() => new Map(links.map((link) => [link.link_id, link])), [links]);

    const runMeasure = useCallback(() => {
        const doc = probeRef.current?.contentDocument;
        if (!doc || !doc.documentElement) {
            setMeasure({ status: 'fallback' });

            return;
        }
        const view = doc.defaultView;
        const scrollX = view?.scrollX ?? 0;
        const scrollY = view?.scrollY ?? 0;
        const boxes: AnchorBox[] = [];
        doc.querySelectorAll<HTMLAnchorElement>('a[data-link-id]').forEach((anchor, index) => {
            const link = linksById.get(anchor.getAttribute('data-link-id') ?? '');
            if (!link) {
                return;
            }
            const rect = anchor.getBoundingClientRect();
            if (rect.width === 0 && rect.height === 0) {
                return;
            }
            boxes.push({
                key: `${link.link_id}-${index}`,
                link,
                top: rect.top + scrollY,
                left: rect.left + scrollX,
                width: rect.width,
                height: rect.height,
            });
        });
        const height = Math.max(doc.documentElement.scrollHeight, doc.body?.scrollHeight ?? 0, 200);
        setMeasure(boxes.length > 0 ? { status: 'ready', height, boxes } : { status: 'fallback' });
    }, [linksById]);

    // Re-measure when the frame width changes (layout reflows synchronously on read).
    useEffect(() => {
        if (probeRef.current?.contentDocument?.readyState === 'complete') {
            runMeasure();
        }
    }, [frameWidth, runMeasure]);

    useEffect(() => setMeasure({ status: 'pending' }), [html]);

    const ranked = [...links]
        .sort((a, b) => b.share - a.share)
        .map((link) => ({
            key: link.id,
            label: link.label || link.url,
            value: link.total_clicks,
            detail: formatText(reportMissingText.linkClicks, formatCount(link.unique_clicks), formatCount(link.total_clicks)),
        }));

    if (loading) {
        return (
            <div ref={rootRef} className={styles.center}>
                <Spinner label={t.common.loading} />
            </div>
        );
    }

    if (!html) {
        return (
            <div ref={rootRef} className={styles.root}>
                <MessageBar intent="info">
                    <MessageBarBody>{reportMissingText.emailUnavailable}</MessageBarBody>
                </MessageBar>
                <HorizontalBars items={ranked} ariaLabel={t.campaignReport.clicks.clickMap} />
            </div>
        );
    }

    return (
        <div ref={rootRef} className={styles.root}>
            <Text size={200} className={styles.help}>
                {t.campaignReport.clicks.clickMapHelp}
            </Text>
            <iframe
                ref={probeRef}
                className={styles.probe}
                title=""
                aria-hidden="true"
                tabIndex={-1}
                sandbox="allow-same-origin"
                srcDoc={html}
                style={{ width: frameWidth, height: PROBE_HEIGHT }}
                onLoad={runMeasure}
            />
            {measure.status === 'fallback' ? (
                <>
                    <MessageBar intent="info">
                        <MessageBarBody>{reportMissingText.clickMapFallback}</MessageBarBody>
                    </MessageBar>
                    <HorizontalBars items={ranked} ariaLabel={t.campaignReport.clicks.clickMap} />
                </>
            ) : (
                <div className={styles.viewport}>
                    <div className={styles.canvas} style={{ width: frameWidth }}>
                        <EmailFrame
                            html={html}
                            title={t.campaignReport.clicks.clickMap}
                            width={frameWidth}
                            height={measure.status === 'ready' ? measure.height : VIEWPORT_HEIGHT}
                        />
                        {measure.status === 'ready' && (
                            <div className={styles.overlay}>
                                {measure.boxes.map((box) => (
                                    <span
                                        key={`outline-${box.key}`}
                                        className={styles.outline}
                                        style={{ top: box.top, left: box.left, width: box.width, height: box.height }}
                                        aria-hidden="true"
                                    />
                                ))}
                                {measure.boxes.map((box) => (
                                    <Tooltip
                                        key={box.key}
                                        relationship="description"
                                        content={`${box.link.label || box.link.url} · ${formatText(
                                            reportMissingText.linkClicks,
                                            formatCount(box.link.unique_clicks),
                                            formatCount(box.link.total_clicks),
                                        )}`}
                                    >
                                        <Badge
                                            className={styles.badge}
                                            appearance="filled"
                                            color="brand"
                                            tabIndex={0}
                                            style={{
                                                top: box.top,
                                                left: Math.min(frameWidth - 24, Math.max(24, box.left + box.width)),
                                            }}
                                        >
                                            {formatRate(box.link.share)}
                                        </Badge>
                                    </Tooltip>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
