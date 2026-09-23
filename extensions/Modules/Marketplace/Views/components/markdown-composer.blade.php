@props([
    'id' => 'marketplace-markdown',
    'placeholder' => 'Describe the resource. Markdown is supported.',
    'rows' => 10,
    'showPreview' => false,
    'previewHtml' => '',
])

<div
    class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
    x-data="{
        wrap(before, after = null) {
            after = after ?? before;
            const ta = this.$refs.editor;
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const selected = ta.value.substring(start, end);
            ta.value = ta.value.substring(0, start) + before + selected + after + ta.value.substring(end);
            ta.selectionStart = start + before.length;
            ta.selectionEnd = start + before.length + selected.length;
            ta.dispatchEvent(new Event('input'));
            ta.focus();
        },
        prefix(marker) {
            const ta = this.$refs.editor;
            const start = ta.selectionStart;
            const lineStart = ta.value.lastIndexOf('\n', start - 1) + 1;
            ta.value = ta.value.substring(0, lineStart) + marker + ta.value.substring(lineStart);
            ta.selectionStart = ta.selectionEnd = start + marker.length;
            ta.dispatchEvent(new Event('input'));
            ta.focus();
        }
    }"
>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-2 py-1.5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-center gap-1">
            <button type="button" class="rounded px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="wrap('**')">B</button>
            <button type="button" class="rounded px-2 py-1 text-xs italic text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="wrap('_')">I</button>
            <button type="button" class="rounded px-2 py-1 font-mono text-xs text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="wrap('`')">&lt;/&gt;</button>
            <button type="button" class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="wrap('[', '](url)')">Link</button>
            <button type="button" class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="prefix('- ')">List</button>
            <button type="button" class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="prefix('# ')">H1</button>
        </div>
        <button type="button" class="rounded px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" wire:click="togglePreview">
            {{ $showPreview ? 'Write' : 'Preview' }}
        </button>
    </div>
    <div>
        @if($showPreview)
            <div class="prose prose-sm dark:prose-invert min-h-[12rem] max-w-none px-4 py-3 text-sm text-gray-700 dark:text-gray-200 [&_*]:text-inherit">
                @if(trim(strip_tags($previewHtml)) === '')
                    <p class="text-gray-400">Nothing to preview.</p>
                @else
                    {!! $previewHtml !!}
                @endif
            </div>
        @else
            <textarea
                {{ $attributes->merge([
                    'id' => $id,
                    'rows' => $rows,
                    'placeholder' => $placeholder,
                    'class' => 'block w-full border-0 bg-transparent px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:ring-0 dark:text-white',
                ]) }}
                x-ref="editor"
            ></textarea>
        @endif
    </div>
</div>
