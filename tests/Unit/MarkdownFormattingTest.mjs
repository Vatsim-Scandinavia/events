import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatMarkdown } from '../../resources/js/lib/markdown-formatting.ts';

for (const [command, expected] of [
    ['bold', '**world**'],
    ['italic', '*world*'],
    ['code', '`world`'],
]) {
    await test(`${command} wraps only the selection and keeps it selected`, () => {
        const result = formatMarkdown('Hello world!', 6, 11, command);
        assert.equal(result.value, `Hello ${expected}!`);
        assert.equal(
            result.value.slice(result.selectionStart, result.selectionEnd),
            'world',
        );
    });
}

await test('formatting at the cursor inserts a selected placeholder', () => {
    const result = formatMarkdown('Hello ', 6, 6, 'bold');
    assert.equal(result.value, 'Hello **text**');
    assert.equal(
        result.value.slice(result.selectionStart, result.selectionEnd),
        'text',
    );
});

await test('a selected link label is preserved and its URL is selected for editing', () => {
    const result = formatMarkdown('Read charts now', 5, 11, 'link');
    assert.equal(result.value, 'Read [charts](https://example.com) now');
    assert.equal(
        result.value.slice(result.selectionStart, result.selectionEnd),
        'https://example.com',
    );
});

for (const [command, expected] of [
    ['heading', '## First\n## Second'],
    ['bullet-list', '- First\n- Second'],
    ['ordered-list', '1. First\n2. Second'],
    ['quote', '> First\n> Second'],
]) {
    await test(`${command} formats whole selected lines without changing surrounding lines`, () => {
        const result = formatMarkdown(
            'Before\nFirst\nSecond\nAfter',
            9,
            20,
            command,
        );
        assert.equal(result.value, `Before\n${expected}\nAfter`);
    });
}

await test('a heading at the start of a blank document has editable placeholder text', () => {
    assert.equal(formatMarkdown('', 0, 0, 'heading').value, '## Heading');
});

await test('a table is separated from surrounding paragraphs and keeps selected content', () => {
    const result = formatMarkdown('Before Text After', 7, 11, 'table');
    assert.equal(
        result.value,
        'Before \n\n| Column 1 | Column 2 |\n| --- | --- |\n| Text | Text |\n\n After',
    );
    assert.equal(
        result.value.slice(result.selectionStart, result.selectionEnd),
        'Column 1',
    );
});
