import { MessageBar, MessageBarBody, MessageBarTitle } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount, formatRate } from '@/Components/Campaigns/campaignUi';
import { ratio } from '@/Components/Reports/reportUi';

export interface AppleMppNoteProps {
    appleMppOpens: number;
    uniqueViews: number;
    trackableViews: number;
}

/**
 * Brevo's Apple Mail Privacy Protection note: proxy opens are counted apart
 * (`appleMppOpens`) from trackable opens (`trackableViews`).
 */
export function AppleMppNote({ appleMppOpens, uniqueViews, trackableViews }: AppleMppNoteProps) {
    const t = useText();
    const k = t.campaignReport.kpis;

    return (
        <MessageBar intent="info" layout="multiline">
            <MessageBarBody>
                <MessageBarTitle>
                    {k.appleMppOpens} : {formatCount(appleMppOpens)} ({formatRate(ratio(appleMppOpens, uniqueViews))}) · {k.trackableOpens} :{' '}
                    {formatCount(trackableViews)}
                </MessageBarTitle>
                {t.campaignReport.appleMppNote}
            </MessageBarBody>
        </MessageBar>
    );
}
