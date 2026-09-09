import { useParams } from 'react-router-dom';
import QuoteWorkspace from '../../../components/purchase/quotes/QuoteWorkspace';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';

export default function QuoteWorkspacePage() {
    const { id } = useParams();

    useSetPageTitle('Supplier Quotes');

    // One card per row: the comparison table scrolls sideways inside its card
    // rather than the page scrolling.
    return <QuoteWorkspace requestId={id} compact />;
}
