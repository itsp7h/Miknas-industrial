import { useParams } from 'react-router-dom';
import QuoteWorkspace from '../../../components/purchase/quotes/QuoteWorkspace';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';

export default function QuoteWorkspacePage() {
    const { id } = useParams();

    // The Blade page titled itself 'Quotes — {request_number}'; the number
    // arrives with the data, so the id stands in until then.
    useSetPageTitle('Supplier Quotes');

    return <QuoteWorkspace requestId={id} />;
}
