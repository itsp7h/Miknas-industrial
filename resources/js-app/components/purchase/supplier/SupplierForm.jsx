import { useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';

export default function SupplierForm({ initialValues, errors, onSubmit, submitting }) {
    const [values, setValues] = useState({
        name: '',
        supplier_code: '',
        category: '',
        email: '',
        phone: '',
        ...initialValues,
    });

    const handleChange = (name, value) => setValues((current) => ({ ...current, [name]: value }));

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSubmit(values); }}>
            <FormField label="Name" name="name" value={values.name} onChange={handleChange} error={errors.name} />
            <FormField label="Supplier code" name="supplier_code" value={values.supplier_code} onChange={handleChange} error={errors.supplier_code} />
            <FormField label="Category" name="category" value={values.category} onChange={handleChange} error={errors.category} />
            <FormField label="Email" name="email" type="email" value={values.email} onChange={handleChange} error={errors.email} />
            <FormField label="Phone" name="phone" value={values.phone} onChange={handleChange} error={errors.phone} />
            <div className="flex justify-end">
                <Button type="submit" loading={submitting}>Save</Button>
            </div>
        </form>
    );
}
