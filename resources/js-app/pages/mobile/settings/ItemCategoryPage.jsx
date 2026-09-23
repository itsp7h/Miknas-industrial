import ItemCategoryCard from '../../../components/settings/itemCategory/ItemCategoryCard';

export default function ItemCategoryPage() {
    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Item Categories</h1>
                <p className="page-subtitle">The sections items are filed under.</p>
            </div>

            <ItemCategoryCard compact />
        </div>
    );
}
