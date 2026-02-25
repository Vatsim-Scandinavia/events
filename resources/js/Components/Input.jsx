export default function Input({
    type = 'text',
    className = '',
    error,
    ...props
}) {
    return (
        <div>
            <input
                type={type}
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
=======
                className={`block w-full border-2 px-3 py-2 focus:border-secondary focus:outline-none sm:text-sm ${
                    error ? 'border-danger' : 'border-grey-300'
                } ${className}`}
>>>>>>> v2
                {...props}
            />
            {error && (
                <p className="mt-1 text-sm text-danger">{error}</p>
            )}
        </div>
    );
}