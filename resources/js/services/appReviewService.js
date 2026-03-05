import axios from 'axios';
import { isWebToNative } from './paymentService';

/**
 * Check with the server if we should prompt for app review,
 * and if so, trigger the native review dialog.
 */
export async function checkAndPromptReview() {
    // Only prompt in native app context
    if (!isWebToNative() || !window.WTN?.appReview) {
        return false;
    }

    try {
        const res = await axios.get('/api/app-review/should-prompt');
        if (!res.data.should_prompt) {
            return false;
        }

        // Trigger native review prompt
        window.WTN.appReview.prompt();

        // Mark as prompted on the server
        await axios.post('/api/app-review/prompted');

        return true;
    } catch {
        return false;
    }
}
