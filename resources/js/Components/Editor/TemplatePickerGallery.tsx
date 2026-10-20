import { Card, CardHeader, CardPreview, Spinner, Text, makeStyles, tokens } from '@fluentui/react-components';
import { DocumentRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';

const useStyles = makeStyles({
    grid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
        gap: tokens.spacingHorizontalM,
    },
    card: { cursor: 'pointer' },
    preview: {
        height: '140px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: tokens.colorNeutralBackground3,
        overflow: 'hidden',
    },
    img: { width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'top' },
});

export interface PickerItem {
    id: string;
    name: string;
    thumbnailUrl: string | null;
    description?: string | null;
}

interface TemplatePickerGalleryProps {
    items: PickerItem[];
    loading: boolean;
    emptyLabel: string;
    disabled?: boolean;
    onSelect: (id: string) => void;
}

/** Selectable thumbnail grid shared by the Galerie / Mes modèles / Campagnes précédentes tabs. */
export function TemplatePickerGallery({ items, loading, emptyLabel, disabled, onSelect }: TemplatePickerGalleryProps) {
    const styles = useStyles();
    const t = useText();

    if (loading) {
        return <Spinner label={t.common.loading} />;
    }
    if (items.length === 0) {
        return <Text>{emptyLabel}</Text>;
    }

    return (
        <div className={styles.grid}>
            {items.map((item) => (
                <Card
                    key={item.id}
                    className={styles.card}
                    focusMode="off"
                    onClick={() => !disabled && onSelect(item.id)}
                    onKeyDown={(event) => {
                        if (!disabled && (event.key === 'Enter' || event.key === ' ')) {
                            event.preventDefault();
                            onSelect(item.id);
                        }
                    }}
                    tabIndex={0}
                    role="button"
                    aria-label={`${t.templatePicker.use} ${item.name}`}
                >
                    <CardPreview className={styles.preview}>
                        {item.thumbnailUrl ? (
                            <img className={styles.img} src={item.thumbnailUrl} alt="" />
                        ) : (
                            <DocumentRegular fontSize={40} />
                        )}
                    </CardPreview>
                    <CardHeader
                        header={<Text weight="semibold">{item.name}</Text>}
                        description={item.description ?? undefined}
                    />
                </Card>
            ))}
        </div>
    );
}
