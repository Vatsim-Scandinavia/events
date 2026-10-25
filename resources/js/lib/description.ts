import { cn } from '@/lib/utils';

/**
 * How an event description's HTML looks, shared by the event page and the editor so they match.
 */
export const descriptionClasses = cn(
    'space-y-3 leading-relaxed text-muted-foreground',
    '[&_a]:font-medium [&_a]:text-foreground [&_a]:underline [&_a]:underline-offset-4',
    '[&_h1]:text-xl [&_h1,&_h2,&_h3,&_h4]:font-semibold [&_h1,&_h2,&_h3,&_h4]:text-foreground [&_h2]:text-lg',
    '[&_ol]:list-decimal [&_strong]:text-foreground [&_ul]:list-disc [&_ul,&_ol]:pl-5',
    '[&_blockquote]:border-l-2 [&_blockquote]:pl-4 [&_hr]:border-border',
    '[&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:font-mono [&_code]:text-sm [&_pre]:overflow-x-auto [&_pre]:rounded-md [&_pre]:bg-muted [&_pre]:p-3',
);
