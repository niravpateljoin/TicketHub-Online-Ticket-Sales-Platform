import api from '../hooks/useApi';
import { unwrapEnvelope } from './unwrap';

export async function getCategories() {
    const response = await api.get('/categories');
    return unwrapEnvelope(response);
}
