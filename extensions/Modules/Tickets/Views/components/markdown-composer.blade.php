@props([
    'id' => 'ticket-markdown',
    'placeholder' => 'Leave a comment',
    'rows' => 8,
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
            <button type="button" class="rounded px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="wrap('**')" title="Bold">B</button>
            <button type="button" class="rounded px-2 py-1 text-xs italic text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="wrap('_')" title="Italic">I</button>
            <button type="button" class="rounded px-2 py-1 font-mono text-xs text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="wrap('`')" title="Code">&lt;/&gt;</button>
            <button type="button" class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="wrap('[', '](url)')" title="Link">Link</button>
            <button type="button" class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="prefix('- ')" title="List">List</button>
            <button type="button" class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="prefix('> ')" title="Quote">Quote</button>
            <button type="button" class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" @click="wrap('```\n', '\n```')" title="Code block">Code block</button>
        </div>
        <button type="button" class="rounded px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" wire:click="togglePreview">
            {{ $showPreview ? 'Write' : 'Preview' }}
        </button>
    </div>
    <div class="bg-white dark:bg-gray-800">
        @if($showPreview)
            <div class="prose dark:prose-invert min-h-[10rem] max-w-none px-4 py-3 text-sm text-gray-800 dark:text-gray-100">
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
