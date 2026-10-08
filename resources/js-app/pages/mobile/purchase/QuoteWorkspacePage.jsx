import { useParams } from 'react-router-dom';
import QuoteWorkspace from '../../../components/purchase/quotes/QuoteWorkspace';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';
import { Hero, MobilePage } from '../../../components/mobile/ui';

export default function QuoteWorkspacePage() {
    const { id } = useParams();

    useSetPageTitle('Supplier Quotes');

    // One card per row: the comparison table scrolls sideways inside its card
    // rather than the page scrolling.
    return (
        <MobilePage>
            <Hero
                zone="purchase" back={{ to: `/app/purchase/pipeline/${id}`, label: 'Pipeline' }}
                title="Supplier quotes" subtitle="Compare what came back, and award"
            />
            <div><QuoteWorkspace requestId={id} compact /></div>
        </MobilePage>
    );
}
