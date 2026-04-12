export default function Card({ title, subtitle, label, labelColor = 'bg-secondary', actions, children }) {
    return (
        <div className="bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
            <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                <div className="flex justify-between items-center gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-lg font-semibold text-neutral-100">{title}</h2>
                            {label && <span className={`px-2 py-0.5 text-[10px] font-bold tracking-wider text-white uppercase ${labelColor}`}>{label}</span>}
                        </div>
                        {subtitle && <p className="text-sm text-neutral-400">{subtitle}</p>}
                    </div>
                    {actions && <div className="shrink-0">{actions}</div>}
                </div>
            </div>
            {children}
        </div>
    );
}