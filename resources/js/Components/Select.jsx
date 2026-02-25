export default function Select({
    className = '',
    error,
    children,
    ...props
}) {
    return (
        <div className="w-full">
            <select
<<<<<<< feat/ui-rework
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
=======
                {...props}
                className={`block w-full border-2 px-3 py-2.5 bg-white text-gray-900 focus:border-secondary focus:outline-none sm:text-sm appearance-none leading-tight transition-colors ${
                    error ? 'border-danger' : 'border-grey-300'
                } ${className}`}
                style={{
                    backgroundImage: `url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e")`,
                    backgroundPosition: `right 0.5rem center`,
                    backgroundRepeat: `no-repeat`,
                    backgroundSize: `1.5em 1.5em`,
                    paddingRight: `2.5rem`
                }}
>>>>>>> v2
            >
                {children}
            </select>
            {error && (
                <p className="mt-1 text-sm text-danger">{error}</p>
            )}
        </div>
    );
}