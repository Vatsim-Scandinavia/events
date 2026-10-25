export { cn } from 'cn';

// "1 shift", "3 shifts"; pass the plural for nouns that don't just take an s, e.g. "roster entries".
export const plural = (count: number, noun: string, nouns = `${noun}s`) => `${count} ${count === 1 ? noun : nouns}`;

// Sign in at `signInPath` (the shared auth.signIn.url), then come back to `returnTo` (a path on this site).
// Link to it with a plain <a>, not an Inertia <Link>: the sign-in provider is on another origin.
export const signInUrl = (signInPath: string, returnTo: string) => `${signInPath}?return=${encodeURIComponent(returnTo)}`;
