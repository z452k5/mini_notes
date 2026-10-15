export interface Note { id: string; title: string; content: string; createdAt: string; updatedAt: string }
export interface NotePage { data: Note[]; total: number; limit: number; offset: number }
async function request<T>(path: string, options?: RequestInit): Promise<T> {
 const response = await fetch(`/api${path}`, { ...options, headers: { 'Content-Type': 'application/json', ...options?.headers } });
 if (!response.ok) { const data = await response.json().catch(() => null); throw new Error(data?.error?.message ?? 'Не удалось выполнить запрос. Попробуйте ещё раз.'); }
 if (response.status === 204) return undefined as T;
 return response.json();
}
export const api = {
 list: (offset = 0) => request<NotePage>(`/notes?limit=100&offset=${offset}`),
 get: (id: string) => request<Note>(`/notes/${id}`),
 create: (data: Pick<Note, 'title' | 'content'>) => request<Note>('/notes', { method: 'POST', body: JSON.stringify(data) }),
 update: (id: string, data: Pick<Note, 'title' | 'content'>) => request<Note>(`/notes/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
 delete: (id: string) => request<void>(`/notes/${id}`, { method: 'DELETE' }),
};
