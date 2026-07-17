// Shared placeholder gradients used across the public sports pages until real
// photos are wired in. Keyed by the same `g1..g5` labels used in the designs.
export const GRADIENTS: Record<string, string> = {
    g1: 'linear-gradient(120deg,#0b3a5e,#1d7fb8)',
    g2: 'linear-gradient(120deg,#123a24,#1c8f5d)',
    g3: 'linear-gradient(120deg,#2a2150,#5d4db0)',
    g4: 'linear-gradient(120deg,#7a3410,#d2691e)',
    g5: 'linear-gradient(120deg,#1c1c1c,#3a3a3a)',
    g6: 'linear-gradient(120deg,#0C7A4E,#15B877)',
    g7: 'linear-gradient(120deg,#5d2c8a,#a25dd1)',
};

export function gradientStyle(key: string): string {
    return GRADIENTS[key] ?? GRADIENTS.g1;
}
