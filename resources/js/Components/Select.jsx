export default function Select({
    className = '',
    error,
    children,
    ...props
}) {
    return (
        <div className="w-full">
            <select
                className={`block w-full px-3 py-2 text-sm
                    bg-white dark:bg-neutral-900
                    text-neutral-900 dark:text-neutral-100
                    border transition-colors
                    focus:outline-none focus:border-primary dark:focus:border-primary
                    ${error
                        ? 'border-danger'
                        : 'border-neutral-300 dark:border-neutral-700'
                    }
                    ${className}`}
                {...props}
            >
                {children}
            </select>
            {error && (
                <p className="mt-1 text-sm text-danger">{error}</p>
            )}
        </div>
    );
}