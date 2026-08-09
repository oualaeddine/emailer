import { Dialog, DialogBody, DialogSurface, makeStyles, tokens } from '@fluentui/react-components';

const useStyles = makeStyles({
    surface: {
        maxWidth: '96vw',
        width: 'fit-content',
    },
    image: {
        display: 'block',
        maxWidth: '100%',
        maxHeight: '82vh',
        borderRadius: tokens.borderRadiusMedium,
    },
});

interface ImageLightboxProps {
    src: string | null;
    alt: string;
    onClose: () => void;
}

/** Full-size view of a documentation screenshot, opened by clicking its thumbnail. */
export function ImageLightbox({ src, alt, onClose }: ImageLightboxProps) {
    const styles = useStyles();

    return (
        <Dialog open={src !== null} onOpenChange={(_, data) => !data.open && onClose()}>
            <DialogSurface className={styles.surface}>
                <DialogBody>{src && <img className={styles.image} src={src} alt={alt} />}</DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
