import { useState } from 'react';
import Button from './Button';

/**
 * Component for managing a list of airport ICAO codes
 *
 * @param {Array} value - Array of airport ICAO codes
 * @param {Function} onChange - Callback when airports change
 * @param {string} error - Error message to display
 */
export default function AirportSelector({ value = [], onChange, error }) {
    const [inputValue, setInputValue] = useState('');
    const [inputError, setInputError] = useState('');

    const handleAdd = () => {
        const icao = inputValue.trim().toUpperCase();

        if (!icao) {
            setInputError('Please enter an airport code');
            return;
        }
        if (icao.length < 3 || icao.length > 4) {
            setInputError('Airport codes must be 3-4 characters');
            return;
        }
        if (!/^[A-Z0-9]+$/.test(icao)) {
            setInputError('Airport codes can only contain letters and numbers');
            return;
        }
        if (value.includes(icao)) {
            setInputError('This airport is already added');
            return;
        }

        onChange([...value, icao]);
        setInputValue('');
        setInputError('');
    };

    const handleRemove = (icao) => {
        onChange(value.filter(a => a !== icao));
    };

    const handleKeyPress = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleAdd();
        }
    };

    return (
        <div className="flex flex-col gap-2">

            {/* Input row */}
            <div className="flex gap-2">
                <input
                    type="text"
                    value={inputValue}
                    onChange={(e) => {
                        setInputValue(e.target.value);
                        setInputError('');
                    }}
                    onKeyPress={handleKeyPress}
                    placeholder="e.g. EKCH, ESSA, ENGM"
                    maxLength={4}
                    className="flex-1 px-3 py-2 text-sm
                        bg-white dark:bg-neutral-900
                        text-neutral-900 dark:text-neutral-100
                        border border-neutral-300 dark:border-neutral-700
                        focus:outline-none focus:border-primary dark:focus:border-primary
                        transition-colors"
                />
                <Button type="button" variant="secondary" onClick={handleAdd}>
                    Add
                </Button>
            </div>

            {/* Validation errors */}
            {inputError && <p className="text-sm text-danger">{inputError}</p>}
            {error && <p className="text-sm text-danger">{error}</p>}

            {/* Airport tags */}
            {value.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {value.map((airport) => (
                        <span
                            key={airport}
                            className="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium bg-secondary dark:bg-secondary/80 text-white"
                        >
                            <span className="font-mono">{airport}</span>
                            <button
                                type="button"
                                onClick={() => handleRemove(airport)}
                                className="ml-1 text-white/70 hover:text-white focus:outline-none transition-colors"
                                aria-label={`Remove ${airport}`}
                            >
                                ×
                            </button>
                        </span>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-neutral-400 dark:text-neutral-500 italic">No airports added yet.</p>
            )}

        </div>
    );
}