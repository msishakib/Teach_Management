<x-filament-widgets::widget>
    <x-filament::section heading="Upload media">
        <form
            data-media-upload
            data-upload-url="{{ route('media.upload') }}"
            data-chunk-init-url="{{ route('media.chunked.initiate') }}"
            data-chunk-status-url="{{ route('media.chunked.status', ['uploadId' => '__UPLOAD_ID__']) }}"
            data-chunk-url="{{ route('media.chunked.append', ['uploadId' => '__UPLOAD_ID__']) }}"
            data-chunk-complete-url="{{ route('media.chunked.complete', ['uploadId' => '__UPLOAD_ID__']) }}"
            data-csrf="{{ csrf_token() }}"
            class="space-y-4"
        >
            <div class="grid gap-4 md:grid-cols-3">
                <label class="text-sm font-medium">
                    Collection
                    <select name="category" required class="fi-input mt-1 w-full rounded-lg">
                        @foreach (\App\Enums\MediaCategory::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium">
                    Attach to
                    <select name="model_type" required class="fi-input mt-1 w-full rounded-lg">
                        @foreach (config('media.upload_models', []) as $alias => $modelClass)
                            <option value="{{ $alias }}">{{ \Illuminate\Support\Str::headline($alias) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium">
                    Record ID
                    <input name="model_id" type="number" min="1" required class="fi-input mt-1 w-full rounded-lg">
                </label>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <label class="min-w-64 flex-1 text-sm font-medium">
                    File
                    <input
                        name="file"
                        type="file"
                        required
                        accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.mp4,.webm"
                        class="fi-input mt-1 w-full rounded-lg"
                    >
                </label>
                <button type="submit" class="fi-btn fi-btn-color-primary fi-size-md">
                    Upload
                </button>
                <button type="button" data-retry hidden class="fi-btn fi-btn-color-gray fi-size-md">
                    Resume / retry
                </button>
            </div>
            <div data-progress-container hidden>
                <div class="mb-1 flex justify-between text-sm">
                    <span data-status>Ready</span>
                    <span data-percent>0%</span>
                </div>
                <progress data-progress class="w-full" max="100" value="0"></progress>
            </div>
        </form>
    </x-filament::section>

    @once
        <script>
            (() => {
                const initMediaUpload = () => {
                    document.querySelectorAll('[data-media-upload]:not([data-initialized])').forEach((form) => {
                        form.dataset.initialized = 'true';
                        const input = form.elements.file;
                        const submit = form.querySelector('[type="submit"]');
                        const retry = form.querySelector('[data-retry]');
                        const container = form.querySelector('[data-progress-container]');
                        const progress = form.querySelector('[data-progress]');
                        const percent = form.querySelector('[data-percent]');
                        const status = form.querySelector('[data-status]');
                        const csrf = form.dataset.csrf;
                        const chunkSize = 5 * 1024 * 1024;
                        let resumableKey = null;
                        let uploadId = null;

                        const storageRead = (key) => {
                            try { return localStorage.getItem(key); } catch (_) { return null; }
                        };
                        const storageWrite = (key, value) => {
                            try { localStorage.setItem(key, value); } catch (_) {}
                        };
                        const storageRemove = (key) => {
                            try { localStorage.removeItem(key); } catch (_) {}
                        };

                        const setProgress = (value, message) => {
                            const bounded = Math.max(0, Math.min(100, Math.round(value)));
                            progress.value = bounded;
                            percent.textContent = `${bounded}%`;
                            status.textContent = message;
                            container.hidden = false;
                        };

                        const jsonRequest = async (url, options = {}) => {
                            const response = await fetch(url, {
                                ...options,
                                headers: {
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    ...(options.headers || {}),
                                },
                            });
                            const body = await response.json().catch(() => ({}));

                            if (!response.ok) {
                                const message = Object.values(body.errors || {}).flat()[0] || body.message || 'Upload request failed.';
                                throw new Error(message);
                            }

                            return body;
                        };

                        const xhrRequest = (url, data, onProgress, method = 'POST') => new Promise((resolve, reject) => {
                            const xhr = new XMLHttpRequest();
                            xhr.open(method, url);
                            xhr.setRequestHeader('Accept', 'application/json');
                            xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
                            xhr.upload.onprogress = (event) => {
                                if (event.lengthComputable) onProgress(event.loaded / event.total);
                            };
                            xhr.onload = () => {
                                let body = {};
                                try { body = JSON.parse(xhr.responseText); } catch (_) {}
                                if (xhr.status >= 200 && xhr.status < 300) return resolve(body);
                                const message = Object.values(body.errors || {}).flat()[0] || body.message || 'Upload failed.';
                                reject(new Error(message));
                            };
                            xhr.onerror = () => reject(new Error('Network error while uploading.'));
                            xhr.send(data);
                        });

                        const uploadChunk = async (index, chunk, completedBytes, file) => {
                            let attempt = 0;
                            while (attempt < 3) {
                                try {
                                    const body = new FormData();
                                    body.append('index', String(index));
                                    body.append('chunk', chunk, `${index}.part`);
                                    await xhrRequest(
                                        form.dataset.chunkUrl.replace('__UPLOAD_ID__', uploadId),
                                        body,
                                        (fraction) => setProgress(((completedBytes + chunk.size * fraction) / file.size) * 100, 'Uploading video…'),
                                        'POST',
                                    );
                                    return;
                                } catch (error) {
                                    attempt++;
                                    if (attempt === 3) throw error;
                                    await new Promise((resolve) => setTimeout(resolve, 750 * attempt));
                                }
                            }
                        };

                        const uploadVideo = async (file) => {
                            const category = form.elements.category.value;
                            const modelType = form.elements.model_type.value;
                            const modelId = form.elements.model_id.value;
                            const nextKey = `media-upload:${category}:${modelType}:${modelId}:${file.name}:${file.size}:${file.lastModified}`;

                            if (resumableKey !== null && resumableKey !== nextKey) uploadId = null;
                            resumableKey = nextKey;
                            const savedId = storageRead(resumableKey);

                            if (savedId) {
                                uploadId = savedId;
                                try {
                                    await jsonRequest(form.dataset.chunkStatusUrl.replace('__UPLOAD_ID__', uploadId));
                                } catch (_) {
                                    uploadId = null;
                                    storageRemove(resumableKey);
                                }
                            }

                            if (!uploadId) {
                                const session = await jsonRequest(form.dataset.chunkInitUrl, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        category,
                                        original_name: file.name,
                                        total_size: file.size,
                                    }),
                                });
                                uploadId = session.upload_id;
                                storageWrite(resumableKey, uploadId);
                            }

                            const sessionUrl = form.dataset.chunkStatusUrl.replace('__UPLOAD_ID__', uploadId);
                            const current = await jsonRequest(sessionUrl);
                            const received = new Set(current.received);
                            const count = Math.ceil(file.size / chunkSize);

                            for (let index = 0; index < count; index++) {
                                const start = index * chunkSize;
                                const chunk = file.slice(start, Math.min(file.size, start + chunkSize));
                                if (received.has(index)) continue;
                                await uploadChunk(index, chunk, start, file);
                            }

                            setProgress(100, 'Finishing upload…');
                            const result = await jsonRequest(
                                form.dataset.chunkCompleteUrl.replace('__UPLOAD_ID__', uploadId),
                                {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ model_type: modelType, model_id: modelId }),
                                },
                            );
                            storageRemove(resumableKey);
                            return result;
                        };

                        const runUpload = async () => {
                            const file = input.files[0];
                            if (!file) return;
                            submit.disabled = true;
                            retry.hidden = true;
                            setProgress(0, 'Starting upload…');

                            try {
                                const result = form.elements.category.value === 'course_videos'
                                    ? await uploadVideo(file)
                                    : await xhrRequest(form.dataset.uploadUrl, (() => {
                                        return new FormData(form);
                                    })(), (fraction) => setProgress(fraction * 100, 'Uploading…'));
                                setProgress(100, `Uploaded ${result.name || file.name}.`);
                                uploadId = null;
                                resumableKey = null;
                            } catch (error) {
                                setProgress(progress.value, error.message);
                                retry.hidden = !uploadId;
                            } finally {
                                submit.disabled = false;
                            }
                        };

                        form.addEventListener('submit', (event) => {
                            event.preventDefault();
                            runUpload();
                        });
                        retry.addEventListener('click', runUpload);
                    });
                };

                initMediaUpload();
                document.addEventListener('livewire:navigated', initMediaUpload);
            })();
        </script>
    @endonce
</x-filament-widgets::widget>
