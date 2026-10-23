export { cn } from 'cn';

// "1 shift", "3 shifts"; pass the plural for nouns that don't just take an s, e.g. "roster entries".
export const plural = (count: number, noun: string, nouns = `${noun}s`) => `${count} ${count === 1 ? noun : nouns}`;

// Sign in with VATSIM, then come back to `returnTo` (a path on this site). Link to it with a plain
// <a>, not an Inertia <Link>: VATSIM Connect is on another origin.
export const signInUrl = (returnTo: string) => `/auth/vatsim?return=${encodeURIComponent(returnTo)}`;
