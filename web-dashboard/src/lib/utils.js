import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * Combine Tailwind CSS classes with clsx and tailwind-merge
 */
export function cn(...inputs) {
  return twMerge(clsx(inputs))
}
