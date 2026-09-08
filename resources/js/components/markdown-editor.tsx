import { useHttp } from '@inertiajs/react';
import {
    Bold,
    Code,
    Eye,
    Heading2,
    Italic,
    Link,
    List,
    ListOrdered,
    Quote,
    Table,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { ComponentProps } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatMarkdown } from '@/lib/markdown-formatting';
import type { MarkdownCommand } from '@/lib/markdown-formatting';
import { markdownPreview } from '@/routes/events';

const commands = [
    { command: 'bold', label: 'Bold', icon: Bold },
    { command: 'italic', label: 'Italic', icon: Italic },
    { command: 'heading', label: 'Heading', icon: Heading2 },
    { command: 'bullet-list', label: 'Bulleted list', icon: List },
    { command: 'ordered-list', label: 'Numbered list', icon: ListOrdered },
    { command: 'quote', label: 'Quote', icon: Quote },
    { command: 'link', label: 'Insert link', icon: Link },
    { command: 'code', label: 'Inline code', icon: Code },
    { command: 'table', label: 'Insert table', icon: Table },
] as const;

type MarkdownEditorProps = Omit<
    ComponentProps<'textarea'>,
    'value' | 'defaultValue' | 'onChange'
> & {
    id: string;
    value: string;
    onChange: (value: string) => void;
};

export function MarkdownEditor({
    id,
    value,
    onChange,
    disabled,
    readOnly,
    maxLength,
    ...props
}: MarkdownEditorProps) {
    const input = useRef<HTMLTextAreaElement>(null);
    const request = useHttp<{ markdown: string }, { html: string }>({
        markdown: '',
    });
    const [preview, setPreview] = useState<{
        markdown: string;
        html: string;
    } | null>(null);
    const previewVisible = preview !== null && preview.markdown === value;
    const cannotEdit = disabled || readOnly;

    function format(command: MarkdownCommand) {
        if (!input.current || cannotEdit) return;
        const formatted = formatMarkdown(
            value,
            input.current.selectionStart,
            input.current.selectionEnd,
            command,
        );
        if (maxLength !== undefined && formatted.value.length > maxLength) {
            toast.error(
                `Formatting would exceed the ${maxLength.toLocaleString('en-US')} character limit.`,
            );
            return;
        }
        onChange(formatted.value);
        setPreview(null);
        requestAnimationFrame(() => {
            input.current?.focus();
            input.current?.setSelectionRange(
                formatted.selectionStart,
                formatted.selectionEnd,
            );
        });
    }

    function togglePreview() {
        if (previewVisible) {
            setPreview(null);
            return;
        }
        if (!value.trim()) {
            setPreview({ markdown: value, html: '' });
            return;
        }
        request.transform(() => ({ markdown: value }));
        void request
            .post(markdownPreview.url(), {
                onSuccess: ({ html }) => setPreview({ markdown: value, html }),
                onError: () =>
                    toast.error(
                        'This description could not be previewed. Check its length and try again.',
                    ),
                onHttpException: () => {
                    toast.error('Preview failed. Please try again.');
                },
                onNetworkError: () => {
                    toast.error('Connection lost. Please try again.');
                },
            })
            .catch(() => {});
    }

    return (
        <div className="flex min-w-0 flex-col gap-2">
            <div
                className="bg-muted/40 flex flex-wrap items-center gap-1 rounded-md border p-1"
                role="group"
                aria-label={`${id === 'short_description' ? 'Short description' : 'Description'} formatting`}
            >
                {commands.map(({ command, label, icon: Icon }) => (
                    <Button
                        key={command}
                        type="button"
                        variant="ghost"
                        size="icon"
                        title={label}
                        aria-label={label}
                        aria-controls={id}
                        disabled={cannotEdit}
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => format(command)}
                    >
                        <Icon />
                    </Button>
                ))}
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="ml-auto"
                    aria-expanded={previewVisible}
                    aria-controls={`${id}-preview`}
                    disabled={disabled || request.processing}
                    onClick={togglePreview}
                >
                    {request.processing ? <Spinner /> : <Eye />}
                    {request.processing
                        ? 'Loading…'
                        : previewVisible
                          ? 'Hide preview'
                          : 'Preview'}
                </Button>
            </div>
            <Textarea
                {...props}
                ref={input}
                id={id}
                value={value}
                maxLength={maxLength}
                disabled={disabled}
                readOnly={readOnly}
                onChange={(event) => {
                    onChange(event.target.value);
                    setPreview(null);
                }}
                onKeyDown={(event) => {
                    props.onKeyDown?.(event);
                    if (
                        event.defaultPrevented ||
                        !(event.ctrlKey || event.metaKey) ||
                        event.altKey ||
                        event.shiftKey
                    )
                        return;
                    const key = event.key.toLowerCase();
                    if (key === 'b' || key === 'i' || key === 'k') {
                        event.preventDefault();
                        format(
                            key === 'b'
                                ? 'bold'
                                : key === 'i'
                                  ? 'italic'
                                  : 'link',
                        );
                    }
                }}
            />
            <div className="text-muted-foreground flex flex-wrap justify-between gap-2 text-xs">
                <span>
                    Markdown · Ctrl/⌘ B for bold, I for italic, K for links
                </span>
                {maxLength !== undefined ? (
                    <span>
                        {value.length.toLocaleString('en-US')} /{' '}
                        {maxLength.toLocaleString('en-US')}
                    </span>
                ) : null}
            </div>
            <div
                id={`${id}-preview`}
                hidden={!previewVisible}
                role="region"
                aria-label="Markdown preview"
                aria-live="polite"
                className="rounded-md border p-4"
            >
                {previewVisible && preview.html ? (
                    <div
                        className="event-markdown"
                        dangerouslySetInnerHTML={{ __html: preview.html }}
                    />
                ) : (
                    <p className="text-muted-foreground text-sm">
                        Nothing to preview yet.
                    </p>
                )}
            </div>
        </div>
    );
}
