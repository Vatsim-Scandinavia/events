import { ImageUp, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { Label } from '@/components/ui/label';

const MAX_LOGO_BYTES = 2 * 1024 * 1024;

// The vACC's logo, which the favicon is made from. SVGs stay vectors; other images are converted on upload.
export function LogoInput({
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
            <Label htmlFor={id}>Logo</Label>
            <input
                ref={input}
                id={id}
                type="file"
                accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg"
                className="sr-only"
                onChange={(e) => {
                    const chosen = e.target.files?.[0];

                    // Caught here so a large file isn't sent only to be refused.
                    setTooLarge(!!chosen && chosen.size > MAX_LOGO_BYTES);

                    if (chosen && chosen.size <= MAX_LOGO_BYTES) {
                        onChange(chosen);
                    }

                    e.target.value = '';
                }}
            />
            {shown ? (
                <div className="flex flex-wrap items-center gap-4">
                    {/* At navbar and tab-icon size, on the dark background it will sit on. */}
                    <div className="flex items-center gap-3 rounded-lg bg-background px-4 py-3 ring-1 ring-foreground/10">
                        <img src={shown} alt="Logo preview" className="size-12 object-contain" />
                        <img src={shown} alt="" className="size-4 object-contain" aria-hidden />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => input.current?.click()}>
                            <ImageUp />
                            Replace
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={onRemove}>
                            <X />
                            {file && current ? 'Keep the current logo' : 'Remove'}
                        </Button>
                        {file && <span className="truncate text-xs text-muted-foreground">{file.name}</span>}
                    </div>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    aria-invalid={!!error}
                    className="flex h-28 w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed text-sm text-muted-foreground transition-colors hover:border-ring hover:text-foreground aria-invalid:border-destructive sm:w-72"
                >
                    <ImageUp className="size-6" />
                    Upload a logo
                </button>
            )}
            <p className="text-xs text-muted-foreground">
                Shown in the navbar and used as the browser tab icon. A square logo works best. SVG, PNG, JPG or WebP up to 2 MB.
            </p>
            <FieldError message={tooLarge ? 'That file is larger than 2 MB. Try a smaller one.' : error} />
        </div>
    );
}
