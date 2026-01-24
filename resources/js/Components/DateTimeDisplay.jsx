import { format, formatInTimeZone } from 'date-fns-tz';
import { useState } from 'react';

/**
 * Display datetime with Zulu time and local time tooltip
 * 
 * @param {Date|string} datetime - The datetime to display
 * @param {string} formatString - date-fns format string (default: 'PPP p')
 * @param {boolean} showIcon - Whether to show a clock icon
 */
export default function DateTimeDisplay({ 
    datetime, 
    formatString = 'PPP HH:mm',
    shortFormat = 'HH:mm',
    showIcon = false,
    className = ''
}) {
    const [showTooltip, setShowTooltip] = useState(false);
    
    // Handle null, undefined, or empty values
    if (!datetime) {
        return <span className={className}>N/A</span>;
    }
    
    const date = typeof datetime === 'string' ? new Date(datetime) : datetime;
    
    // Check if date is valid
    if (isNaN(date.getTime())) {
        return <span className={className}>Invalid date</span>;
    }
    
    // Format in UTC (Zulu time) with custom handling
    const zuluDate = formatInTimeZone(date, 'UTC', 'PPP');
    const zuluTime = formatInTimeZone(date, 'UTC', 'HH:mm');
    const zuluDisplay = `${zuluDate}, ${zuluTime}Z`;
    
    // Format in local timezone (24-hour format)
    const localTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const localTime = format(date, formatString);
    const localTzAbbr = format(date, 'zzz');
    
    return (
        <div className={`relative inline-block ${className}`}>
            <span
                className="cursor-help border-b border-dotted border-gray-400"
                onMouseEnter={() => setShowTooltip(true)}
                onMouseLeave={() => setShowTooltip(false)}
            >
                {showIcon && <span className="mr-1">🕐</span>}
                {zuluDisplay}
            </span>
            
            {showTooltip && (
                <div className="absolute z-50 px-3 py-2 text-sm font-medium text-white bg-gray-900 bottom-full left-1/2 transform -translate-x-1/2 mb-2 whitespace-nowrap">
                    <div>Local: {localTime}</div>
                    <div className="text-xs text-gray-300 mt-1">({localTzAbbr})</div>
                    <div className="tooltip-arrow" data-popper-arrow></div>
                </div>
            )}
        </div>
    );
}

/**
 * Display just the time portion with tooltip
 */
export function TimeDisplay({ datetime, showIcon = false, className = '' }) {
    const [showTooltip, setShowTooltip] = useState(false);
    
    // Handle null, undefined, or empty values
    if (!datetime) {
        return <span className={className}>N/A</span>;
    }
    
    // Check if datetime is already in HH:mm format (time-only string)
    // Also handle HH:mm:ss format from database
    const isTimeOnly = typeof datetime === 'string' && /^([0-1][0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/.test(datetime);
    
    if (isTimeOnly) {
        // Extract just HH:mm from HH:mm:ss if needed
        const timeOnly = datetime.substring(0, 5);
        // Just display the time directly (already in UTC/HH:mm format)
        return (
            <span className={className}>
                {showIcon && <span className="mr-1">🕐</span>}
                {timeOnly}Z
            </span>
        );
    }
    
    // Otherwise, it's a full datetime - format it
    const date = typeof datetime === 'string' ? new Date(datetime) : datetime;
    
    // Check if date is valid
    if (isNaN(date.getTime())) {
        return <span className={className}>Invalid time</span>;
    }
    
    // Format in UTC (Zulu time)
    const zuluTime = formatInTimeZone(date, 'UTC', 'HH:mm');
    
    // Format in local timezone
    const localTime = format(date, 'HH:mm');
    const localTzAbbr = format(date, 'zzz');
    
    return (
        <div className={`relative inline-block ${className}`}>
            <span
                className="cursor-help border-b border-dotted border-gray-400"
                onMouseEnter={() => setShowTooltip(true)}
                onMouseLeave={() => setShowTooltip(false)}
            >
                {showIcon && <span className="mr-1">🕐</span>}
                {zuluTime}Z
            </span>
            
            {showTooltip && (
                <div className="absolute z-50 px-3 py-2 text-sm font-medium text-white bg-gray-900 bottom-full left-1/2 transform -translate-x-1/2 mb-2 whitespace-nowrap">
                    <div>Local: {localTime} ({localTzAbbr})</div>
                </div>
            )}
        </div>
    );
}

/**
 * Display date and time range with tooltip
 */
export function DateTimeRangeDisplay({ start, end, className = '' }) {
    const [showTooltip, setShowTooltip] = useState(false);
    
    // Handle null, undefined, or empty values
    if (!start || !end) {
        return <span className={className}>N/A</span>;
    }
    
    const startDate = typeof start === 'string' ? new Date(start) : start;
    const endDate = typeof end === 'string' ? new Date(end) : end;
    
    // Check if dates are valid
    if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
        return <span className={className}>Invalid date range</span>;
    }
    
    // Format in UTC (Zulu time)
    const zuluDate = formatInTimeZone(startDate, 'UTC', 'PPP');
    const zuluStartTime = formatInTimeZone(startDate, 'UTC', 'HH:mm');
    const zuluEndTime = formatInTimeZone(endDate, 'UTC', 'HH:mm');
    
    // Format in local timezone
    const localTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const localDate = format(startDate, 'PPP');
    const localStartTime = format(startDate, 'HH:mm');
    const localEndTime = format(endDate, 'HH:mm');
    const localTzAbbr = format(startDate, 'zzz');
    
    return (
        <div className={`relative inline-block ${className}`}>
            <span
                className="cursor-help border-b border-dotted border-gray-400"
                onMouseEnter={() => setShowTooltip(true)}
                onMouseLeave={() => setShowTooltip(false)}
            >
                {zuluDate} • {zuluStartTime}-{zuluEndTime}Z
            </span>
            
            {showTooltip && (
                <div className="absolute z-50 px-3 py-2 text-sm font-medium text-white bg-gray-900 bottom-full left-1/2 transform -translate-x-1/2 mb-2 whitespace-nowrap">
                    <div className="font-semibold mb-1">Local Time ({localTzAbbr})</div>
                    <div>{localDate}</div>
                    <div>{localStartTime} - {localEndTime}</div>
                </div>
            )}
        </div>
    );
}
