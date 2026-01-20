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
    const handleChange = (e) => {
        onChange(e.target.value);
    };

    return (
        <div>
            <input
                type="time"
                value={value || ''}
                onChange={handleChange}
                placeholder={placeholder}
                required={required}
                className={`w-full border-2 px-3 py-2 focus:outline-none sm:text-sm ${
                    error ? 'border-danger' : 'border-grey-300 focus:border-secondary'
                }`}
            />
            {error && (
                <p className="mt-1 text-sm text-danger">{error}</p>
            )}
            <p className="mt-1 text-xs text-gray-500 dark:text-dark-text-secondary">
                24-hour format (UTC)
            </p>
        </div>
    );
}
