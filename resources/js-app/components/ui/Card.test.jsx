import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import Card from './Card';

describe('Card', () => {
    it('renders a title and children', () => {
        render(<Card title="Supplier summary">Body</Card>);

        expect(screen.getByText('Supplier summary')).toBeInTheDocument();
        expect(screen.getByText('Body')).toBeInTheDocument();
    });
});
