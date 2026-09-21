import ItemCategoryCard from '../../../components/settings/itemCategory/ItemCategoryCard';

export default function ItemCategoryPage() {
    return (
        <div>
            <div className="mb-5">
                <h1 className="page-title">Item Categories</h1>
                <p className="page-subtitle">
                    The sections items are filed under — the second half of &ldquo;Raw Materials / Chemical Materials&rdquo;.
                </p>
            </div>

            <ItemCategoryCard />
        </div>
    );
}
