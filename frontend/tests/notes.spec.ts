import { expect, test } from '@playwright/test';
test('create, read, edit and delete a note on one page', async ({ page, request }) => {
 const title = `Заметка ${Date.now()}`;
 let id: string | undefined;
 try {
  await page.goto('/');
  await expect(page.getByRole('heading', { name: /^Заметки/ })).toBeVisible();
  await page.getByRole('button', { name: 'Новая заметка', exact: true }).click();
  await page.getByLabel('Заголовок', { exact: true }).fill(title);
  await page.getByLabel('Текст заметки', { exact: true }).fill('Сохраняется в MySQL 📝');
  const create = page.waitForResponse(r => r.url().endsWith('/api/notes') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Сохранить', exact: true }).click();
  const response = await create;
  expect(response.status()).toBe(201);
  id = (await response.json()).id;
  await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
  await page.reload();
  await page.getByRole('button', { name: `Открыть: ${title}`, exact: true }).click();
  await expect(page.getByText('Сохраняется в MySQL 📝', { exact: true }).last()).toBeVisible();
  await page.getByRole('button', { name: 'Редактировать', exact: true }).click();
  await expect(page.getByLabel('Текст заметки', { exact: true })).toHaveValue('Сохраняется в MySQL 📝');
  await page.getByLabel('Заголовок', { exact: true }).fill(`${title} изменена`);
  await page.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(page.getByRole('heading', { name: `${title} изменена`, exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Удалить', exact: true }).click();
  await expect(page.getByRole('dialog').getByRole('button', { name: 'Удалить', exact: true })).toBeInViewport();
  await page.getByRole('dialog').getByRole('button', { name: 'Отмена', exact: true }).click();
  await expect(page.getByRole('heading', { name: `${title} изменена`, exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Удалить', exact: true }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Удалить', exact: true }).click();
  await expect(page.getByRole('button', { name: `Открыть: ${title} изменена`, exact: true })).toHaveCount(0);
  id = undefined;
 } finally { if (id) await request.delete(`/api/notes/${id}`); }
});
test('mobile form validates title and can be cancelled', async ({ page }) => {
 await page.setViewportSize({ width: 390, height: 844 });
 await page.goto('/');
 await page.getByRole('button', { name: 'Новая заметка', exact: true }).click();
 await page.getByLabel('Заголовок', { exact: true }).fill('   ');
 await page.getByRole('button', { name: 'Сохранить', exact: true }).click();
 await expect(page.locator('#title-error')).toHaveText('Введите заголовок.');
 await page.getByLabel('Заголовок', { exact: true }).fill('Несохранённая заметка');
 await page.getByRole('button', { name: 'Отмена', exact: true }).click();
 await expect(page.getByLabel('Заголовок', { exact: true })).toHaveCount(0);
 expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
});

test('server validation displays field errors, counters and preserves input', async ({ page, request }) => {
 const title = 'я'.repeat(51);
 const content = '📝'.repeat(201);
 let id: string | undefined;
 try {
  await page.goto('/');
  await page.getByRole('button', { name: 'Новая заметка', exact: true }).click();
  await page.getByLabel('Заголовок', { exact: true }).fill(title);
  await page.getByLabel('Текст заметки', { exact: true }).fill(content);
  await expect(page.getByText('51 / 50', { exact: true })).toBeVisible();
  await expect(page.getByText('201 / 200', { exact: true })).toBeVisible();
  const rejected = page.waitForResponse(r => r.url().endsWith('/api/notes') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Сохранить', exact: true }).click();
  expect((await rejected).status()).toBe(422);
  await expect(page.locator('#title-error')).toHaveText('Заголовок должен содержать не более 50 символов.');
  await expect(page.locator('#content-error')).toHaveText('Текст должен содержать не более 200 символов.');
  await expect(page.getByLabel('Заголовок', { exact: true })).toHaveValue(title);
  await expect(page.getByLabel('Текст заметки', { exact: true })).toHaveValue(content);
  await page.getByLabel('Заголовок', { exact: true }).fill(title.slice(0, 50));
  await page.getByLabel('Текст заметки', { exact: true }).fill('📝'.repeat(200));
  const accepted = page.waitForResponse(r => r.url().endsWith('/api/notes') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Сохранить', exact: true }).click();
  const response = await accepted;
  expect(response.status()).toBe(201); id = (await response.json()).id;
  await expect(page.getByRole('status')).toHaveText('Заметка сохранена.');
 } finally { if (id) await request.delete(`/api/notes/${id}`); }
});

test('delete confirmation keeps its title throughout the closing animation', async ({ page, request }) => {
 const title = `Закрытие ${Date.now()}`;
 const response = await request.post('/api/notes', { data: { title, content: '' } });
 expect(response.status()).toBe(201);
 const note = await response.json();
 try {
  await page.goto('/');
  await page.getByRole('button', { name: `Открыть: ${title}`, exact: true }).click();
  await page.getByRole('button', { name: 'Удалить', exact: true }).click();
  await expect(page.getByRole('dialog')).toContainText(`«${title}»`);
  const observed = page.evaluate(() => new Promise<string | null>(resolve => {
   const dialog = document.querySelector('[role="dialog"]')!;
   const observer = new MutationObserver(() => {
    if (dialog.getAttribute('data-state') === 'closed') {
     resolve(dialog.textContent); observer.disconnect();
    }
   });
   observer.observe(dialog, { attributes: true, childList: true, subtree: true });
  }));
  await page.getByRole('dialog').getByRole('button', { name: 'Отмена', exact: true }).click();
  expect(await observed).toContain(`«${title}»`);
  await expect(page.getByRole('dialog')).toHaveCount(0);
 } finally { await request.delete(`/api/notes/${note.id}`); }
});
