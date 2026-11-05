import React from 'react';
import { BadgeTone } from '../domain/types';

const DOT_CLASSES: Record<BadgeTone, string> = {
  neutral: 'bg-ink-3',
  blue: 'bg-[#080B0C] dark:bg-[#C6F432]',
  teal: 'bg-teal-600 dark:bg-teal-400',
  amber: 'bg-amber-600 dark:bg-amber-400',
  orange: 'bg-orange-600 dark:bg-orange-400',
  green: 'bg-emerald-600 dark:bg-emerald-400',
  red: 'bg-rose-600 dark:bg-rose-400',
  live: 'bg-amber-600 dark:bg-amber-400',
  'outline-green': 'bg-emerald-600 dark:bg-emerald-400',
};

const TEXT_CLASSES: Record<BadgeTone, string> = {
  neutral: 'text-ink-2',
  blue: 'text-ink',
  teal: 'text-teal-800 dark:text-teal-300',
  amber: 'text-amber-800 dark:text-amber-300',
  orange: 'text-orange-800 dark:text-orange-300',
  green: 'text-emerald-800 dark:text-emerald-300',
  red: 'text-rose-800 dark:text-rose-300',
  live: 'text-amber-800 dark:text-amber-300',
  'outline-green': 'text-emerald-800 dark:text-emerald-300',
};

export const Badge: React.FC<{
  tone?: BadgeTone;
  children: React.ReactNode;
  className?: string;
}> = ({ tone = 'neutral', children, className = '' }) => {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded border border-line bg-tint px-2 py-0.5 text-xs font-semibold whitespace-nowrap shrink-0 ${
        TEXT_CLASSES[tone] ?? TEXT_CLASSES.neutral
      } ${className}`}
    >
      <span
        className={`size-1.5 shrink-0 rounded-full ${
          DOT_CLASSES[tone] ?? DOT_CLASSES.neutral
        }`}
      />
      <span>{children}</span>
    </span>
  );
};
