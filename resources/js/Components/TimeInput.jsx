/**
 * Time input using native HTML5 time picker
 *
 * @param {string} value - The time value in HH:mm format (e.g., "14:30")
 * @param {Function} onChange - Callback when time changes, receives the time string
 * @param {string} error - Error message to display
 * @param {string} placeholder - Placeholder text
 * @param {boolean} required - Whether the field is required
 */
export default function TimeInput({
    value,
    onChange,
    error,
    placeholder = '12:00',
    required = false
}) {
    return (
        <div>
            <input
                type="time"
                value={value || ''}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                required={required}
                className={`w-full px-3 py-2 text-sm
                    bg-white dark:bg-neutral-900
                    text-neutral-900 dark:text-neutral-100
                    border transition-colors
                    focus:outline-none focus:border-primary dark:focus:border-primary
                    scheme-light dark:scheme-dark
                    ${error
                        ? 'border-danger'
                        : 'border-neutral-300 dark:border-neutral-700'
                    }`}
            />
            {error && (
                <p className="mt-1 text-sm text-danger">{error}</p>
            )}
            <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                24-hour format (UTC)
            </p>
        </div>
    );
}