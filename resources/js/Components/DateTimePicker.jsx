import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';

/**
 * Date and time picker component that displays and works in UTC
 * 
 * @param {Date|string} selected - The selected date/time (UTC)
 * @param {Function} onChange - Callback when date/time changes
 * @param {Date} minDate - Minimum selectable date
 * @param {Date} maxDate - Maximum selectable date
 * @param {string} error - Error message to display
 * @param {string} placeholderText - Placeholder text
 * @param {boolean} required - Whether the field is required
 */
export default function DateTimePicker({ 
    selected, 
    onChange, 
    minDate,
    maxDate,
    error, 
    placeholderText = 'Select date and time (UTC)',
    required = false
}) {
    // Convert UTC datetime to a Date object that displays as UTC in the picker
    // The trick: create a "local" date that when displayed shows UTC time
    let displayDate = null;
    if (selected) {
        const utcDate = typeof selected === 'string' ? new Date(selected) : selected;
        // Get UTC components
        const year = utcDate.getUTCFullYear();
        const month = utcDate.getUTCMonth();
        const day = utcDate.getUTCDate();
        const hours = utcDate.getUTCHours();
        const minutes = utcDate.getUTCMinutes();
        // Create a "local" date with these values (will display as-is)
        displayDate = new Date(year, month, day, hours, minutes);
    }

    const handleChange = (date) => {
        if (date) {
            // Convert local date back to UTC
            const year = date.getFullYear();
            const month = date.getMonth();
            const day = date.getDate();
            const hours = date.getHours();
            const minutes = date.getMinutes();
            // Create UTC date from these values
            const utcDate = new Date(Date.UTC(year, month, day, hours, minutes));
            onChange(utcDate);
        } else {
            onChange(null);
        }
    };

    return (
        <div>
            <DatePicker
                selected={displayDate}
                onChange={handleChange}
                showTimeSelect
                timeFormat="HH:mm"
                timeIntervals={15}
                timeCaption="Time (UTC)"
                dateFormat="MMMM d, yyyy HH:mm 'Z'"
                minDate={minDate}
                maxDate={maxDate}
                placeholderText={placeholderText}
                required={required}
                className={`w-full border-2 px-3 py-2 focus:outline-none sm:text-sm ${
                    error ? 'border-danger' : 'border-grey-300 focus:border-secondary'
                }`}
                calendarClassName="date-picker-calendar"
                wrapperClassName="w-full"
            />
            {error && (
                <p className="mt-1 text-sm text-danger">{error}</p>
            )}
        </div>
    );
}
