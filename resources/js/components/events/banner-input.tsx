import { ImageUp, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { Label } from '@/components/ui/label';

const MAX_BANNER_BYTES = 8 * 1024 * 1024;

// Banners are converted to WebP on upload, so any common image format is accepted here.
export function BannerInput({
    current,
    file,
    error,
    onChange,
    onRemove,
}: {
    current: string | null;
    file: File | null;
    error?: string;
    onChange: (file: File) => void;
    onRemove: () => void;
}) {
    const id = useId();
    const input = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [tooLarge, setTooLarge] = useState(false);

    useEffect(() => {
        if (!file) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const shown = preview ?? current;

    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={id}>Banner image</Label>
            <input
                ref={input}
                id={id}
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                className="sr-only"
                onChange={(e) => {
                    const chosen = e.target.files?.[0];

                    // Caught here so a large file isn't sent only to be refused.
                    setTooLarge(!!chosen && chosen.size > MAX_BANNER_BYTES);

                    if (chosen && chosen.size <= MAX_BANNER_BYTES) {
                        onChange(chosen);
                    }

                    e.target.value = '';
                }}
            />
            {shown ? (
                <div className="flex flex-col gap-2">
                    <img src={shown} alt="Banner preview" className="aspect-[3/1] w-full rounded-lg object-cover ring-1 ring-foreground/10" />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => input.current?.click()}>
                            <ImageUp />
                            Replace
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={onRemove}>
                            <X />
                            {file && current ? 'Keep the current banner' : 'Remove'}
                        </Button>
                        {file && <span className="truncate text-xs text-muted-foreground">{file.name}</span>}
                    </div>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    aria-invalid={!!error}
                    className="flex aspect-[3/1] w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed text-sm text-muted-foreground transition-colors hover:border-ring hover:text-foreground aria-invalid:border-destructive"
                >
                    <ImageUp className="size-6" />
                    Upload a banner
                </button>
            )}
            <p className="text-xs text-muted-foreground">
                A wide image, at least 600 pixels across. JPG, PNG, WebP or GIF up to 8 MB; it’s saved as WebP.
            </p>
            <FieldError message={tooLarge ? 'That image is larger than 8 MB. Try a smaller one.' : error} />
        </div>
    );
}
