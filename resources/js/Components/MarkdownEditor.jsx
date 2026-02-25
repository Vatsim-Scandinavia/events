import { useMemo } from 'react';
import SimpleMDE from 'react-simplemde-editor';
import 'easymde/dist/easymde.min.css';

/**
 * Markdown editor component using SimpleMDE
 *
 * @param {string} value - The markdown content
 * @param {Function} onChange - Callback when content changes
 * @param {string} error - Error message to display
 * @param {string} placeholder - Placeholder text
 */
export default function MarkdownEditor({ value, onChange, error, placeholder = 'Enter description...' }) {
    const options = useMemo(() => ({
        spellChecker: false,
        placeholder,
        status: false,
        toolbar: [
            'bold', 'italic', 'heading', '|',
            'unordered-list', 'ordered-list', '|',
            'link', 'quote', 'code', '|',
            'preview', 'side-by-side', 'fullscreen', '|',
            'guide',
        ],
        minHeight: '300px',
    }), [placeholder]);

    return (
        <div className="markdown-editor-wrap">
            <SimpleMDE
                value={value}
                onChange={onChange}
                options={options}
            />
            {error && (
                <p className="mt-1 text-sm text-danger">{error}</p>
            )}

            <style>{`
                /* Light mode link styling */
                .markdown-editor-wrap .editor-preview a,
                .markdown-editor-wrap .editor-preview-side a {
                    color: #0891b2;
                    text-decoration: underline;
                }
                .markdown-editor-wrap .editor-preview a:hover,
                .markdown-editor-wrap .editor-preview-side a:hover {
                    color: #0e7490;
                }
                
                /* Dark mode styles */
                .dark .markdown-editor-wrap .EasyMDEContainer .CodeMirror {
                    background: #171717;
                    color: #f5f5f5;
                    border-color: #404040;
                }
                .dark .markdown-editor-wrap .EasyMDEContainer .CodeMirror-cursor {
                    border-left-color: #f5f5f5;
                }
                .dark .markdown-editor-wrap .EasyMDEContainer .CodeMirror-selected {
                    background: #404040;
                }
                .dark .markdown-editor-wrap .editor-toolbar {
                    background: #262626;
                    border-color: #404040;
                }
                .dark .markdown-editor-wrap .editor-toolbar button,
                .dark .markdown-editor-wrap .editor-toolbar button.active,
                .dark .markdown-editor-wrap .editor-toolbar i.separator {
                    color: #d4d4d4;
                    border-color: transparent;
                }
                .dark .markdown-editor-wrap .editor-toolbar button:hover {
                    background: #404040;
                    color: #f5f5f5;
                    border-color: transparent;
                }
                .dark .markdown-editor-wrap .editor-preview {
                    background: #171717;
                    color: #f5f5f5;
                }
                .dark .markdown-editor-wrap .editor-preview-side {
                    background: #171717;
                    color: #f5f5f5;
                    border-color: #404040;
                }
                .dark .markdown-editor-wrap .EasyMDEContainer .CodeMirror-scroll {
                    background: #171717;
                }
                .dark .markdown-editor-wrap .EasyMDEContainer {
                    border-color: #404040;
                }
                
                /* Dark mode link styling */
                .dark .markdown-editor-wrap .editor-preview a,
                .dark .markdown-editor-wrap .editor-preview-side a {
                    color: #22d3ee;
                    text-decoration: underline;
                }
                .dark .markdown-editor-wrap .editor-preview a:hover,
                .dark .markdown-editor-wrap .editor-preview-side a:hover {
                    color: #06b6d4;
                }
            `}</style>
        </div>
    );
}