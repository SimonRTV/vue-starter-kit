import { computed, ref } from 'vue';

type FileDropzoneOptions = {
    disabled: () => boolean;
    extensions: () => string[];
    maxSizeKb: () => number;
    onSelect: (file: File) => void;
    onError: (message: string) => void;
    maxFiles?: () => number;
    onSelectMany?: (files: File[]) => void;
};

export function useFileDropzone(options: FileDropzoneOptions) {
    const dragDepth = ref(0);
    const isDragging = computed(
        () => dragDepth.value > 0 && !options.disabled(),
    );

    function selectFiles(files: File[]): void {
        if (options.disabled() || files.length === 0) {
            return;
        }

        const maxFiles = options.maxFiles?.() ?? 1;

        if (maxFiles === 1) {
            if (files.length !== 1) {
                options.onError(
                    'Veuillez sélectionner un seul fichier à la fois.',
                );

                return;
            }

            const error = validate(files[0]);

            if (error) {
                options.onError(error);

                return;
            }

            options.onSelect(files[0]);

            return;
        }

        if (files.length > maxFiles) {
            options.onError(
                `Vous pouvez sélectionner au maximum ${maxFiles} fichiers à la fois.`,
            );

            return;
        }

        const errors = files.flatMap((file) => {
            const error = validate(file);

            return error ? [`${file.name} : ${error}`] : [];
        });
        const accepted = files.filter((file) => !validate(file));

        if (accepted.length) {
            options.onSelectMany?.(accepted);
        }

        if (errors.length) {
            options.onError(errors.join(' '));
        }
    }

    function validate(file: File): string | null {
        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

        if (!options.extensions().includes(extension)) {
            return 'Ce format de fichier n’est pas autorisé.';
        }

        if (file.size > options.maxSizeKb() * 1024) {
            return 'Ce fichier dépasse la taille maximale autorisée.';
        }

        return null;
    }

    function dragEnter(event: DragEvent): void {
        event.preventDefault();

        if (
            !options.disabled() &&
            event.dataTransfer?.types.includes('Files')
        ) {
            dragDepth.value += 1;
        }
    }

    function dragLeave(event: DragEvent): void {
        event.preventDefault();
        dragDepth.value = Math.max(0, dragDepth.value - 1);
    }

    function dragOver(event: DragEvent): void {
        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = options.disabled()
                ? 'none'
                : 'copy';
        }
    }

    function drop(event: DragEvent): void {
        event.preventDefault();
        dragDepth.value = 0;
        selectFiles(Array.from(event.dataTransfer?.files ?? []));
    }

    return { isDragging, selectFiles, dragEnter, dragLeave, dragOver, drop };
}
