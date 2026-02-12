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
                className={`block w-full border-2 px-3 py-2 focus:border-secondary focus:outline-none sm:text-sm ${
                    error ? 'border-danger' : 'border-grey-300'
                } ${className}`}
                {...props}
            />
            {error && (
                <p className="mt-1 text-sm text-danger">{error}</p>
            )}
        </div>
    );
}
