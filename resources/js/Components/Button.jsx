export default function Button({
    type = 'button',
    className = '',
    variant = 'primary',
    size = 'md',
    children,
    ...props
}) {
    const baseClasses = 'inline-flex items-center border font-semibold transition-colors duration-150 ease-in-out focus:outline-none';

    const sizes = {
        sm: 'px-3 py-1 text-xs',
        md: 'px-4 py-2 text-sm',
        lg: 'px-6 py-3 text-base',
    };

    const variants = {
        primary:           'border-primary bg-primary text-white hover:bg-primary/90 hover:border-primary/90 disabled:bg-neutral-400 disabled:border-neutral-400 disabled:cursor-not-allowed',
        secondary:         'border-secondary bg-secondary text-white hover:bg-secondary/90 hover:border-secondary/90 disabled:bg-neutral-400 disabled:border-neutral-400 disabled:cursor-not-allowed',
        success:           'border-success bg-success text-white hover:bg-success/90 hover:border-success/90 disabled:bg-neutral-400 disabled:border-neutral-400 disabled:cursor-not-allowed',
        danger:            'border-danger bg-danger text-white hover:bg-danger/90 hover:border-danger/90 disabled:bg-neutral-400 disabled:border-neutral-400 disabled:cursor-not-allowed',
        warning:           'border-warning bg-warning text-white hover:bg-warning/90 hover:border-warning/90 disabled:bg-neutral-400 disabled:border-neutral-400 disabled:cursor-not-allowed',
        outline:           'border-primary text-primary bg-transparent hover:bg-primary hover:text-white disabled:border-neutral-400 disabled:text-neutral-400 disabled:cursor-not-allowed',
        'outline-primary':   'border-primary text-primary bg-transparent hover:bg-primary hover:text-white disabled:border-neutral-400 disabled:text-neutral-400 disabled:cursor-not-allowed',
        'outline-secondary': 'border-secondary text-secondary bg-transparent hover:bg-secondary hover:text-white disabled:border-neutral-400 disabled:text-neutral-400 disabled:cursor-not-allowed',
        'outline-success':   'border-success text-success bg-transparent hover:bg-success hover:text-white disabled:border-neutral-400 disabled:text-neutral-400 disabled:cursor-not-allowed',
        'outline-danger':    'border-danger text-danger bg-transparent hover:bg-danger hover:text-white disabled:border-neutral-400 disabled:text-neutral-400 disabled:cursor-not-allowed',
        'outline-warning':   'border-warning text-warning bg-transparent hover:bg-warning hover:text-white disabled:border-neutral-400 disabled:text-neutral-400 disabled:cursor-not-allowed',
        'outline-light':     'border-white text-white bg-transparent hover:bg-white hover:text-secondary disabled:border-neutral-400 disabled:text-neutral-400 disabled:cursor-not-allowed',
    };

    return (
        <button
            type={type}
            className={`${baseClasses} ${sizes[size]} ${variants[variant]} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}