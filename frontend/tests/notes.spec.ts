import { expect, test } from '@playwright/test';
test('create, read, edit and delete a note on one page', async ({ page, request }) => {
 const title = `Браузерная заметка ${Date.now()} ${'я'.repeat(120)}`;
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
 await expect(page.getByRole('button', { name: 'Сохранить', exact: true })).toBeDisabled();
 await page.getByLabel('Заголовок', { exact: true }).fill('   ');
 await expect(page.getByRole('button', { name: 'Сохранить', exact: true })).toBeDisabled();
 await page.getByLabel('Заголовок', { exact: true }).fill('Несохранённая заметка');
 await page.getByRole('button', { name: 'Отмена', exact: true }).click();
 await expect(page.getByLabel('Заголовок', { exact: true })).toHaveCount(0);
 expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
});
