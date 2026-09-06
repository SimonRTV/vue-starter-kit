import { computed, ref } from 'vue';

type FileDropzoneOptions = {
    disabled: () => boolean;
    extensions: () => string[];
    maxSizeKb: () => number;
    onSelect: (file: File) => void;
    onError: (message: string) => void;
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

        if (files.length !== 1) {
            options.onError('Veuillez sélectionner un seul fichier à la fois.');

            return;
        }

        const file = files[0];
        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

        if (!options.extensions().includes(extension)) {
            options.onError('Ce format de fichier n’est pas autorisé.');

            return;
        }

        if (file.size > options.maxSizeKb() * 1024) {
            options.onError('Ce fichier dépasse la taille maximale autorisée.');

            return;
        }

        options.onSelect(file);
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
