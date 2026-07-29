import Modal from './Modal';
import Button from './Button';

export default function ConfirmModal({ open, title, body, onConfirm, onCancel }) {
    return (
        <Modal open={open} title={title} onClose={onCancel}>
            <p className="text-sm text-gray-600 mb-6">{body}</p>
            <div className="flex justify-end gap-3">
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button variant="danger" onClick={onConfirm}>Confirm</Button>
            </div>
        </Modal>
    );
}
