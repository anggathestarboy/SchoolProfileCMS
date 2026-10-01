import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';

// Video/file assets have no dedicated schema node in Tiptap's starter kit —
// without one, a <video>/<a download> inserted into the doc gets stripped on
// the next parse, which is why non-image uploads previously "did nothing".
// These render the same tags the front end later gets from getHTML(), so a
// plain <video controls> / downloadable <a> is all that's needed for both.
const Video = Node.create({
    name: 'video',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        return { src: { default: null } };
    },
    parseHTML() {
        return [{ tag: 'video[src]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['video', mergeAttributes(HTMLAttributes, { controls: 'controls' })];
    },
});

const Audio = Node.create({
    name: 'audio',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        return { src: { default: null } };
    },
    parseHTML() {
        return [{ tag: 'audio[src]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['audio', mergeAttributes(HTMLAttributes, { controls: 'controls' })];
    },
});

const FileAttachment = Node.create({
    name: 'fileAttachment',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        return {
            src: { default: null },
            filename: { default: null },
        };
    },
    parseHTML() {
        return [{ tag: 'a[data-file-attachment]' }];
    },
    renderHTML({ HTMLAttributes, node }) {
        return [
            'a',
            mergeAttributes(HTMLAttributes, { href: node.attrs.src, download: node.attrs.filename, 'data-file-attachment': '', class: 'bard-file' }),
            `\u{1F4CE} ${node.attrs.filename || node.attrs.src}`,
        ];
    },
});

// Picks the schema node a selected asset belongs in, keyed by mime type —
// used both for inserting a new one and for swapping one node type for
// another when "Replace" picks a file of a different kind.
const mediaNodeFor = ({ url, mime, filename }) => {
    if ((mime || '').startsWith('image/')) {
        return { type: 'image', attrs: { src: url } };
    }

    if ((mime || '').startsWith('video/')) {
        return { type: 'video', attrs: { src: url } };
    }

    if ((mime || '').startsWith('audio/')) {
        return { type: 'audio', attrs: { src: url } };
    }

    return { type: 'fileAttachment', attrs: { src: url, filename } };
};

document.addEventListener('alpine:init', () => {
    Alpine.data('bardEditor', (content, model, container) => {
        // Kept as a plain closure variable, NOT a property on the returned
        // object below — Alpine wraps every property of an x-data object in
        // a reactive Proxy, and ProseMirror's transaction system relies on
        // strict `===` identity checks internally (e.g. `tr.before ===
        // state.doc`). A reactive-proxied Editor instance fails those
        // checks and throws "Applying a mismatched transaction" on every
        // command. This is the same reason Tiptap's own Vue integration
        // wraps its editor in Vue's markRaw().
        let editor = null;

        // Position (in the ProseMirror doc) of the image node being replaced
        // via the toolbar menu — set right before opening the asset picker,
        // consumed once "bard-image-selected" reports back with mode "replace".
        let replacingPos = null;

        // The shared media::asset-picker (see entry-form.blade.php) reports
        // its selection back via a global "assets-selected" browser event
        // keyed by "path" — since a bard field has no "assets" data
        // property to write into, we open it with a sentinel path and the
        // Livewire component (EntryForm/TermForm/GlobalSetForm) forwards
        // the resolved image URLs to us via "bard-image-selected" instead.
        const onMediaSelected = (event) => {
            if (event.detail.prefix !== model) {
                return;
            }

            if (event.detail.mode === 'replace') {
                if (replacingPos !== null) {
                    const pos = replacingPos;
                    replacingPos = null;

                    editor
                        .chain()
                        .focus()
                        .deleteRange({ from: pos, to: pos + 1 })
                        .insertContentAt(pos, mediaNodeFor(event.detail.items[0]))
                        .run();
                }

                return;
            }

            // A single insertContent call with all nodes at once — inserting
            // them one chain().run() at a time left only the last one, since
            // each insert leaves the new atom node selected and the next
            // insertContent then replaces that selection instead of adding
            // after it.
            editor
                .chain()
                .focus()
                .insertContent(event.detail.items.map(mediaNodeFor))
                .run();
        };

        return {
            tick: 0,
            imageMenu: null,

            init() {
                editor = new Editor({
                    element: this.$refs.editor,
                    // Link and Underline are already bundled by StarterKit
                    // in Tiptap v3 — importing them separately registered
                    // each extension twice, which also corrupts transactions.
                    extensions: [StarterKit.configure({ link: { openOnClick: false } }), Image, Video, Audio, FileAttachment],
                    content: content || '',
                    onUpdate: ({ editor }) => {
                        this.imageMenu = null;
                        this.$wire.set(model, editor.isEmpty ? '' : editor.getHTML());
                    },
                    onTransaction: () => {
                        this.tick++;
                    },
                });

                window.addEventListener('bard-image-selected', onMediaSelected);
            },

            onEditorClick(event) {
                const mediaEl = event.target.closest('img, video, audio, [data-file-attachment]');

                if (!mediaEl) {
                    this.imageMenu = null;

                    return;
                }

                // Prevents the file attachment's own <a href> from navigating
                // away while editing — the front end still gets a real,
                // clickable download link since it renders the same markup.
                event.preventDefault();

                const pos = editor.view.posAtDOM(mediaEl, 0);
                const imageRect = mediaEl.getBoundingClientRect();
                const containerRect = this.$refs.editor.getBoundingClientRect();

                editor.chain().setNodeSelection(pos).run();

                this.imageMenu = {
                    pos,
                    top: imageRect.bottom - containerRect.top + 4,
                    left: imageRect.left - containerRect.left,
                };
            },

            replaceImage() {
                if (this.imageMenu === null) {
                    return;
                }

                replacingPos = this.imageMenu.pos;
                this.imageMenu = null;

                this.$dispatch('open-asset-picker', {
                    path: ['__bard__', model, 'replace'],
                    container,
                    selected: [],
                });
            },

            deleteImage() {
                if (this.imageMenu === null) {
                    return;
                }

                const { pos } = this.imageMenu;
                editor.chain().focus().deleteRange({ from: pos, to: pos + 1 }).run();
                this.imageMenu = null;
            },

            isActive(name, attrs = {}) {
                this.tick;

                return editor?.isActive(name, attrs) ?? false;
            },

            run(chainMethod, ...args) {
                editor.chain().focus()[chainMethod](...args).run();
            },

            setLink() {
                const url = window.prompt('URL');

                if (url === null) {
                    return;
                }

                if (url === '') {
                    editor.chain().focus().unsetLink().run();

                    return;
                }

                editor.chain().focus().setLink({ href: url }).run();
            },

            insertImage() {
                this.$dispatch('open-asset-picker', {
                    path: ['__bard__', model],
                    container,
                    selected: [],
                });
            },

            destroy() {
                window.removeEventListener('bard-image-selected', onMediaSelected);
                editor?.destroy();
            },
        };
    });
});
