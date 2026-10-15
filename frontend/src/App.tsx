import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { api, ApiError, type FieldErrors, type Note } from '@/lib/api';

type Draft = { id?: string; title: string; content: string };
const formatDate = (value: string) => new Date(value).toLocaleString('ru-RU');
const message = (error: unknown) => error instanceof Error ? error.message : 'Не удалось выполнить запрос.';

export default function App() {
  const [notes, setNotes] = useState<Note[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [status, setStatus] = useState('');
  const [selected, setSelected] = useState<Note | null>(null);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [pendingDelete, setPendingDelete] = useState<Note | null>(null);
  const [deleteOpen, setDeleteOpen] = useState(false);

  function changeDraft(field: 'title' | 'content', value: string) {
    setDraft(previous => previous && { ...previous, [field]: value });
    setFieldErrors(previous => { const next = { ...previous }; delete next[field]; return next; });
    setError('');
  }

  async function load() {
    setLoading(true); setError('');
    try { const page = await api.list(); setNotes(page.data); setTotal(page.total); }
    catch (error) { setError(message(error)); }
    finally { setLoading(false); }
  }
  useEffect(() => { void load(); }, []);

  async function open(note: Note) {
    setBusy(true); setError(''); setStatus('');
    try { setSelected(await api.get(note.id)); setDraft(null); }
    catch (error) { setError(message(error)); }
    finally { setBusy(false); }
  }
  function create() {
    setFieldErrors({}); setDraft({ title: '', content: '' }); setSelected(null); setError(''); setStatus('');
  }
  async function save() {
    if (!draft || busy) return;
    setBusy(true); setError(''); setStatus(''); setFieldErrors({});
    try {
      const data = { title: draft.title, content: draft.content };
      const saved = draft.id ? await api.update(draft.id, data) : await api.create(data);
      setNotes(previous => [saved, ...previous.filter(note => note.id !== saved.id)]);
      if (!draft.id) setTotal(previous => previous + 1);
      setSelected(saved); setDraft(null); setStatus('Заметка сохранена.');
    } catch (error) {
      if (error instanceof ApiError) setFieldErrors(error.fields);
      setError(message(error));
    }
    finally { setBusy(false); }
  }
  async function remove(note: Note) {
    setBusy(true); setError(''); setStatus('');
    try {
      await api.delete(note.id);
      setNotes(previous => previous.filter(item => item.id !== note.id));
      setTotal(previous => previous - 1);
      setSelected(null); setDraft(null); setDeleteOpen(false); setStatus('Заметка удалена.');
    } catch (error) { setError(message(error)); }
    finally { setBusy(false); }
  }
  async function more() {
    setBusy(true); setError('');
    try {
      const page = await api.list(notes.length);
      setNotes(previous => [...previous, ...page.data.filter(note => !previous.some(item => item.id === note.id))]);
      setTotal(page.total);
    } catch (error) { setError(message(error)); }
    finally { setBusy(false); }
  }

  return <main className="page">
    <header className="page-header">
      <h1>Заметки <span>{total}</span></h1>
      <div className="header-actions">
        <Button variant="outline" asChild><a href="/docs/" target="_blank" rel="noreferrer">Swagger API</a></Button>
        <Button disabled={busy || loading} onClick={create}>Новая заметка</Button>
      </div>
    </header>
    {error && !draft && <div className="error" role="alert">{error} <Button variant="outline" size="sm" disabled={busy || loading} onClick={() => void load()}>Обновить список</Button></div>}
    {status && <p className="status" role="status">{status}</p>}
    <div className="columns">
      <section className="panel notes-list" aria-label="Список заметок" aria-busy={loading}>
        {loading ? <p className="muted">Загрузка…</p> : notes.length === 0 ? <p className="muted">Заметок пока нет.</p> : <ul>
          {notes.map(note => <li key={note.id}><button className={`note-row ${selected?.id === note.id ? 'selected' : ''}`} disabled={busy} onClick={() => void open(note)} aria-label={`Открыть: ${note.title}`} aria-current={selected?.id === note.id ? 'true' : undefined}>
            <strong>{note.title}</strong>
            <span className="preview">{note.content || 'Без текста'}</span>
            <time dateTime={note.updatedAt}>{formatDate(note.updatedAt)}</time>
          </button></li>)}
        </ul>}
        {!loading && notes.length < total && <Button className="load-more" variant="outline" disabled={busy} onClick={() => void more()}>Загрузить ещё</Button>}
      </section>
      <section className="panel detail" aria-label="Содержимое заметки">
        {draft ? <form noValidate className="editor-form" onSubmit={event => { event.preventDefault(); void save(); }}>
          <h2>{draft.id ? 'Редактирование заметки' : 'Новая заметка'}</h2>
          <div className="field">
            <label htmlFor="note-title">Заголовок</label>
            <Input id="note-title" required disabled={busy} value={draft.title} aria-invalid={!!fieldErrors.title || [...draft.title].length > 50} aria-describedby="title-hint title-error" onChange={event => changeDraft('title', event.target.value)} />
            <div className="field-meta"><p id="title-hint" className="field-hint">От 1 до 50 символов.</p><span className={`counter ${[...draft.title].length > 50 ? 'over-limit' : ''}`} aria-live="polite">{[...draft.title].length} / 50</span></div>
            {fieldErrors.title && <p id="title-error" className="field-error" role="alert">{fieldErrors.title.join(' ')}</p>}
          </div>
          <div className="field">
            <label htmlFor="note-content">Текст заметки</label>
            <Textarea id="note-content" rows={5} disabled={busy} value={draft.content} aria-invalid={!!fieldErrors.content || [...draft.content].length > 200} aria-describedby="content-hint content-error" onChange={event => changeDraft('content', event.target.value)} />
            <div className="field-meta"><p id="content-hint" className="field-hint">До 200 символов. Можно оставить пустым.</p><span className={`counter ${[...draft.content].length > 200 ? 'over-limit' : ''}`} aria-live="polite">{[...draft.content].length} / 200</span></div>
            {fieldErrors.content && <p id="content-error" className="field-error" role="alert">{fieldErrors.content.join(' ')}</p>}
          </div>
          {error && <p className="form-error" role="alert">{error}</p>}
          <div className="actions form-actions"><Button type="button" variant="outline" disabled={busy} onClick={() => setDraft(null)}>Отмена</Button><Button type="submit" disabled={busy}>{busy ? 'Сохранение…' : 'Сохранить'}</Button></div>
        </form> : selected ? <article>
          <h2>{selected.title}</h2>
          <p className="note-content">{selected.content || 'Без текста'}</p>
          <p className="muted">Изменена: <time dateTime={selected.updatedAt}>{formatDate(selected.updatedAt)}</time></p>
          <div className="actions"><Button disabled={busy} onClick={() => { setFieldErrors({}); setError(''); setDraft({ id: selected.id, title: selected.title, content: selected.content }); setStatus(''); }}>Редактировать</Button><Button variant="destructive" disabled={busy} onClick={() => { setPendingDelete(selected); setDeleteOpen(true); }}>Удалить</Button></div>
        </article> : <p className="muted">Выберите заметку из списка или создайте новую.</p>}
      </section>
    </div>
    <Dialog open={deleteOpen} onOpenChange={open => { if (!busy) setDeleteOpen(open); }}>
      <DialogContent><DialogHeader><DialogTitle>Удалить заметку?</DialogTitle><DialogDescription>«{pendingDelete?.title}» будет удалена без возможности восстановления.</DialogDescription></DialogHeader><DialogFooter><Button variant="outline" disabled={busy} onClick={() => setDeleteOpen(false)}>Отмена</Button><Button variant="destructive" disabled={busy} onClick={() => { if (pendingDelete) void remove(pendingDelete); }}>{busy ? 'Удаление…' : 'Удалить'}</Button></DialogFooter></DialogContent>
    </Dialog>
  </main>;
}
