import { makeStyles, tokens, mergeClasses } from '@fluentui/react-components';
import { MailFilled } from '@fluentui/react-icons';

const useStyles = makeStyles({
    root: {
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        flexShrink: 0,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorBrandBackground,
        color: tokens.colorNeutralForegroundOnBrand,
    },
});

interface BrandMarkProps {
    size?: number;
    className?: string;
}

/** Small branded app mark — a rounded red square with a mail glyph, used in the top bar and on the login screen. */
export function BrandMark({ size = 32, className }: BrandMarkProps) {
    const styles = useStyles();

    return (
        <span
            className={mergeClasses(styles.root, className)}
            style={{ width: size, height: size }}
            aria-hidden="true"
        >
            <MailFilled fontSize={size * 0.55} />
        </span>
    );
}
