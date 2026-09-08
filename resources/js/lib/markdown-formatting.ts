export type MarkdownCommand =
    | 'bold'
    | 'italic'
    | 'heading'
    | 'bullet-list'
    | 'ordered-list'
    | 'quote'
    | 'link'
    | 'code'
    | 'table';

export function formatMarkdown(
    value: string,
    start: number,
    end: number,
    command: MarkdownCommand,
) {
    const selected = value.slice(start, end);
    let replacement: string;
    let selectionStart = start;
    let selectionEnd: number;

    if (
        command === 'heading' ||
        command === 'bullet-list' ||
        command === 'ordered-list' ||
        command === 'quote'
    ) {
        start = start === 0 ? 0 : value.lastIndexOf('\n', start - 1) + 1;
        const lineEnd = value.indexOf(
            '\n',
            end > start && value[end - 1] === '\n' ? end - 1 : end,
        );
        end = lineEnd === -1 ? value.length : lineEnd;
        const content =
            value.slice(start, end) ||
            (command === 'heading' ? 'Heading' : 'List item');
        replacement = content
            .split('\n')
            .map((line, index) => {
                const prefix =
                    command === 'heading'
                        ? '## '
                        : command === 'quote'
                          ? '> '
                          : command === 'ordered-list'
                            ? `${index + 1}. `
                            : '- ';
                return prefix + line;
            })
            .join('\n');
        selectionStart = start;
        selectionEnd = start + replacement.length;
    } else if (command === 'table') {
        const before = value.slice(0, start);
        const after = value.slice(end);
        const prefix =
            before && !before.endsWith('\n\n')
                ? before.endsWith('\n')
                    ? '\n'
                    : '\n\n'
                : '';
        const suffix =
            after && !after.startsWith('\n\n')
                ? after.startsWith('\n')
                    ? '\n'
                    : '\n\n'
                : '\n';
        replacement = `${prefix}| Column 1 | Column 2 |\n| --- | --- |\n| ${selected || 'Text'} | Text |${suffix}`;
        selectionStart = start + prefix.length + 2;
        selectionEnd = selectionStart + 'Column 1'.length;
    } else {
        const marker =
            command === 'bold'
                ? '**'
                : command === 'italic'
                  ? '*'
                  : command === 'code'
                    ? '`'
                    : '[';
        const content =
            selected ||
            (command === 'link'
                ? 'Link text'
                : command === 'code'
                  ? 'code'
                  : 'text');
        const suffix = command === 'link' ? '](https://example.com)' : marker;
        replacement = marker + content + suffix;
        selectionStart = start + marker.length;
        selectionEnd = selectionStart + content.length;
        if (command === 'link' && selected) {
            selectionStart = start + marker.length + content.length + 2;
            selectionEnd = selectionStart + 'https://example.com'.length;
        }
    }

    return {
        value: value.slice(0, start) + replacement + value.slice(end),
        selectionStart,
        selectionEnd,
    };
}
