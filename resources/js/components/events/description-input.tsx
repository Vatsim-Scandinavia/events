import { Markdown } from '@tiptap/markdown';
import { EditorContent, useEditor, useEditorState, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Heading2, Italic, Link2, List, ListOrdered, type LucideIcon } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { descriptionClasses } from '@/lib/description';
import { cn } from '@/lib/utils';

/**
 * Edits an event description visually while reading and writing Markdown, so what is stored
 * and how the server renders it stay the same. Typing Markdown (**bold**, "- ", "## ") works too.
 */
export function DescriptionInput({ value, error, onChange }: { value: string; error?: string; onChange: (markdown: string) => void }) {
    const labelId = useId();
    const errorId = useId();

    const editor = useEditor({
        // Only what the event page renders: underline has no Markdown form.
        extensions: [
            StarterKit.configure({ heading: { levels: [1, 2, 3] }, underline: false, link: { openOnClick: false } }),
            // Descriptions written before Markdown rely on single newlines becoming line breaks, as on the server.
            Markdown.configure({ markedOptions: { breaks: true } }),
        ],
        content: value,
        contentType: 'markdown',
        immediatelyRender: false,
        // The editor owns its content after mount; setting it back from props would move the cursor.
        onUpdate: ({ editor: updated }) => onChange(updated.getMarkdown()),
        editorProps: {
            attributes: {
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-labelledby': labelId,
                ...(error ? { 'aria-invalid': 'true', 'aria-describedby': errorId } : {}),
                class: cn(descriptionClasses, 'min-h-36 px-2.5 py-2 text-base outline-none md:text-sm'),
            },
        },
    });

    return (
        // Not a <Field>: its <label> would pass clicks on the label text to the first toolbar button.
        <div className="flex flex-col gap-1.5">
            <Label id={labelId} onClick={() => editor?.commands.focus()}>
                Description
            </Label>
            <div
                className={cn(
                    'rounded-lg border border-input transition-colors focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 dark:bg-input/30',
                    error && 'border-destructive ring-3 ring-destructive/20',
                )}
            >
                {editor && <Toolbar editor={editor} />}
                <EditorContent editor={editor} />
            </div>
            <span className="text-sm text-muted-foreground">Format with the toolbar, or type Markdown like **bold**, - lists and ## headings.</span>
            <FieldError id={errorId} message={error} />
        </div>
    );
}

function Toolbar({ editor }: { editor: Editor }) {
    const active = useEditorState({
        editor,
        selector: ({ editor: current }) => ({
            bold: current.isActive('bold'),
            italic: current.isActive('italic'),
            heading: current.isActive('heading', { level: 2 }),
            bulletList: current.isActive('bulletList'),
            orderedList: current.isActive('orderedList'),
            link: current.isActive('link'),
        }),
    });

    const editLink = () => {
        const url = window.prompt('Link address (leave empty to remove the link)', editor.getAttributes('link').href ?? 'https://');

        if (url === null) {
            return;
        }

        const chain = editor.chain().focus().extendMarkRange('link');
        (url.trim() === '' ? chain.unsetLink() : chain.setLink({ href: url.trim() })).run();
    };

    const buttons: { label: string; icon: LucideIcon; pressed: boolean; run: () => void }[] = [
        { label: 'Bold', icon: Bold, pressed: active.bold, run: () => editor.chain().focus().toggleBold().run() },
        { label: 'Italic', icon: Italic, pressed: active.italic, run: () => editor.chain().focus().toggleItalic().run() },
        { label: 'Heading', icon: Heading2, pressed: active.heading, run: () => editor.chain().focus().toggleHeading({ level: 2 }).run() },
        { label: 'Bulleted list', icon: List, pressed: active.bulletList, run: () => editor.chain().focus().toggleBulletList().run() },
        { label: 'Numbered list', icon: ListOrdered, pressed: active.orderedList, run: () => editor.chain().focus().toggleOrderedList().run() },
        { label: 'Link', icon: Link2, pressed: active.link, run: editLink },
    ];

    return (
        <div role="toolbar" aria-label="Formatting" className="flex flex-wrap gap-0.5 border-b border-input p-1">
            {buttons.map(({ label, icon: Icon, pressed, run }) => (
                <Button
                    key={label}
                    type="button"
                    variant={pressed ? 'secondary' : 'ghost'}
                    size="icon"
                    aria-label={label}
                    aria-pressed={pressed}
                    title={label}
                    onClick={run}
                >
                    <Icon />
                </Button>
            ))}
        </div>
    );
}
