export { cn } from 'cn';

export const plural = (count: number, noun: string) => `${count} ${noun}${count === 1 ? '' : 's'}`;

// Sign in with VATSIM, then come back to `returnTo` (a path on this site). Link to it with a plain
// <a>, not an Inertia <Link>: VATSIM Connect is on another origin.
export const signInUrl = (returnTo: string) => `/auth/vatsim?return=${encodeURIComponent(returnTo)}`;
