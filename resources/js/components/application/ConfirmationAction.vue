<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import type { ButtonVariants } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        confirmLabel: string;
        pendingLabel?: string;
        cancelLabel?: string;
        confirmVariant?: ButtonVariants['variant'];
        processing?: boolean;
    }>(),
    {
        pendingLabel: 'Traitement…',
        cancelLabel: 'Annuler',
        confirmVariant: 'destructive',
        processing: false,
    },
);

const emit = defineEmits<{
    confirm: [];
}>();

const open = defineModel<boolean>('open', { default: false });

function updateOpen(value: boolean): void {
    if (props.processing && !value) {
        return;
    }

    open.value = value;
}

function confirm(): void {
    if (props.processing) {
        return;
    }

    emit('confirm');
}
</script>

<template>
    <AlertDialog :open="open" @update:open="updateOpen">
        <AlertDialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ description }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <slot name="details" />

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processing">
                    {{ cancelLabel }}
                </AlertDialogCancel>
                <Button
                    type="button"
                    :variant="confirmVariant"
                    :disabled="processing"
                    @click="confirm"
                >
                    <Spinner v-if="processing" data-icon="inline-start" />
                    {{ processing ? pendingLabel : confirmLabel }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
