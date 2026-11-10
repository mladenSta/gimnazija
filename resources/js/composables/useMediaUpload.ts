import { reactive, ref } from 'vue';
import { HttpError, uploadFile } from '@/lib/http';
import { store } from '@/routes/admin/media';
import type { MediaItem, UploadSettings } from '@/types/media';

export type UploadStatus = 'queued' | 'uploading' | 'done' | 'error';

export type UploadTask = {
    key: number;
    file: File;
    name: string;
    progress: number;
    status: UploadStatus;
    error: string | null;
    media: MediaItem | null;
};

const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Upload queue: validates files in the browser, then uploads two at a time
 * (one file per request keeps each request under the server limit).
 */
export function useMediaUpload(
    settings: Pick<UploadSettings, 'maxKb'>,
    onUploaded?: (media: MediaItem) => void,
) {
    const tasks = ref<UploadTask[]>([]);
    const busy = ref(false);
    let nextKey = 1;

    function validate(file: File): string | null {
        if (!ACCEPTED.includes(file.type)) {
            return 'Dozvoljeni formati su JPG, PNG i WebP.';
        }

        if (file.size > settings.maxKb * 1024) {
            return `Fotografija je prevelika (najviše ${Math.round(settings.maxKb / 1024)} MB).`;
        }

        return null;
    }

    function add(files: FileList | File[]): void {
        for (const file of Array.from(files)) {
            const error = validate(file);

            tasks.value.unshift(
                reactive({
                    key: nextKey++,
                    file,
                    name: file.name,
                    progress: 0,
                    status: error ? 'error' : 'queued',
                    error,
                    media: null,
                }) as UploadTask,
            );
        }

        void run();
    }

    async function uploadOne(task: UploadTask): Promise<void> {
        task.status = 'uploading';

        try {
            const { data } = await uploadFile<{ data: MediaItem }>(
                store.url(),
                task.file,
                (p) => (task.progress = p),
            );
            task.status = 'done';
            task.progress = 100;
            task.media = data.data;
            onUploaded?.(data.data);
        } catch (e) {
            task.status = 'error';
            task.error =
                e instanceof HttpError
                    ? e.firstMessage()
                    : 'Otpremanje nije uspjelo.';
        }
    }

    async function run(): Promise<void> {
        if (busy.value) {
            return;
        }

        busy.value = true;

        const worker = async (): Promise<void> => {
            let task: UploadTask | undefined;

            while ((task = tasks.value.find((t) => t.status === 'queued'))) {
                await uploadOne(task);
            }
        };

        await Promise.all([worker(), worker()]);
        busy.value = false;
    }

    function clearFinished(): void {
        tasks.value = tasks.value.filter(
            (t) => t.status === 'queued' || t.status === 'uploading',
        );
    }

    return { tasks, busy, add, clearFinished };
}
