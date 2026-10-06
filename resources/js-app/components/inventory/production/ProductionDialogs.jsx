import Modal from '../../ui/Modal';
import { useToast } from '../../ui/Toast';
import ProductionRunForm from './ProductionRunForm';
import RecipeEditor from './RecipeEditor';
import RunDetail from './RunDetail';
import useProductionOptions from './useProductionOptions';

/**
 * Fetches the options when it mounts — i.e. when a dialog opens — so stock on
 * hand is as of opening the form, then renders its child with them.
 */
function WithOptions({ children }) {
    const { options, loaded, error, setRecipe } = useProductionOptions();

    if (error) return <p className="text-sm text-red-600">{error}</p>;
    if (!loaded) return <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>;

    return children(options, setRecipe);
}

/** The three dialogs of Inventory → Production, shared by both viewports. */
export default function ProductionDialogs({ p, compact = false }) {
    const { showToast } = useToast();

    return (
        <>
            <Modal open={p.formOpen} title="New Production Run" onClose={() => p.setFormOpen(false)} maxWidth="56rem">
                <WithOptions>
                    {(options) => (
                        <ProductionRunForm
                            options={options} compact={compact} onCancel={() => p.setFormOpen(false)}
                            onSaved={(run, message) => {
                                p.handleSaved(run);
                                showToast(message ?? 'Production recorded.', 'success');
                            }}
                        />
                    )}
                </WithOptions>
            </Modal>

            <Modal open={p.recipesOpen} title="Recipes" onClose={() => p.setRecipesOpen(false)} maxWidth="40rem">
                <WithOptions>
                    {(options, setRecipe) => (
                        <RecipeEditor
                            options={options} compact={compact} onCancel={() => p.setRecipesOpen(false)}
                            onSaved={(saved, message) => {
                                setRecipe(saved.product_id, saved.recipe);
                                showToast(message ?? 'Recipe saved.', 'success');
                            }}
                        />
                    )}
                </WithOptions>
            </Modal>

            <Modal
                open={!!p.viewing} title={p.viewing ? `Production Run ${p.viewing.run_number}` : ''}
                onClose={() => p.setViewing(null)} maxWidth="44rem"
            >
                {p.viewing && <RunDetail run={p.viewing} compact={compact} />}
            </Modal>
        </>
    );
}
