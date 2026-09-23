import { useEffect, useState } from 'react';
import Modal from './Modal';
import Button from './Button';

/**
 * `confirmWord` turns this into a typed confirmation: Confirm stays disabled
 * until the word is typed exactly. For an action that cannot be undone and is
 * not aimed at one visible row, a click on its own is too easy to make by
 * accident. Leave it unset and the dialog behaves as it always has.
 */
export default function ConfirmModal({ open, title, body, confirmWord, onConfirm, onCancel }) {
    const [typed, setTyped] = useState('');

    // Each opening starts empty, so a previous confirmation cannot arm this one.
    useEffect(() => {
        if (open) {
            setTyped('');
        }
    }, [open]);

    const armed = !confirmWord || typed.trim().toUpperCase() === confirmWord.toUpperCase();

    return (
        <Modal open={open} title={title} onClose={onCancel}>
            <p className="text-sm text-gray-600 mb-6">{body}</p>
            {confirmWord && (
                <div style={{ marginBottom: 24 }}>
                    <label className="form-label" htmlFor="confirm-word">
                        Type <strong>{confirmWord}</strong> to confirm
                    </label>
                    <input
                        id="confirm-word" type="text" className="form-input"
                        value={typed} onChange={(e) => setTyped(e.target.value)}
                        autoComplete="off" style={{ width: '100%' }}
                    />
                </div>
            )}
            <div className="flex justify-end gap-3">
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button variant="danger" onClick={onConfirm} disabled={!armed}>Confirm</Button>
            </div>
        </Modal>
    );
}
