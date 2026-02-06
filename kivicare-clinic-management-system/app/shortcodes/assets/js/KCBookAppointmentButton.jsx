import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ToastProvider } from '@app/components/KCToast';
import BookAppointmentForm from './KCBookAppointment';
import '@shortcodes/assets/scss/KCBookAppointment.scss';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            refetchOnWindowFocus: false,
            retry: 1
        }
    }
});

const KCBookAppointmentButton = ({ 
    containerId, 
    formId,
    title,
    isKivicarePro,
    widgetOrder,
    userLogin,
    paymentGateways,
    currentUserId,
    pageId,
    queryParams,
    showPrintButton
}) => {
    return (
        <QueryClientProvider client={queryClient}>
            <ToastProvider>
                <BookAppointmentForm
                    id={containerId}
                    formId={formId}
                    title={title}
                    isKivicarePro={isKivicarePro}
                    widgetOrder={widgetOrder}
                    userLogin={userLogin}
                    paymentGateways={paymentGateways}
                    currentUserId={currentUserId}
                    pageId={pageId}
                    queryParams={queryParams}
                    showPrintButton={showPrintButton}
                />
            </ToastProvider>
        </QueryClientProvider>
    );
};

document.addEventListener('DOMContentLoaded', () => {
    const containers = document.querySelectorAll('.kc-book-appointment-button-container:not([data-react-initialized])');
    
    containers.forEach(container => {
        container.setAttribute('data-react-initialized', 'true');
        
        const root = createRoot(container);
        root.render(
            <KCBookAppointmentButton 
                containerId={container.id}
                formId={container.dataset.formId || '0'}
                title={container.dataset.title || ''}
                isKivicarePro={container.dataset.isKivicarePro || 'false'}
                widgetOrder={container.dataset.widgetOrder || '[]'}
                userLogin={container.dataset.userLogin || '0'}
                paymentGateways={container.dataset.paymentGateways || '[]'}
                currentUserId={container.dataset.currentUserId || '0'}
                pageId={container.dataset.pageId || '0'}
                queryParams={container.dataset.queryParams || '{}'}
                showPrintButton={container.dataset.showPrintButton || 'false'}
            />
        );
    });
});