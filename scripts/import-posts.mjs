// Converts the Markdown blog drafts in content/posts/*.md into database/seed/posts.json,
// which the installer (or `php app/cli.php import-posts`) loads into MySQL as drafts.
//
// File format — any number of posts per file, each starting with a front-matter block:
// ---
// title: …            slug: …            category: <slug from _categories.json>
// keyword: …          metaTitle: …       metaDescription: …
// excerpt: …          tags: a, b, c      image: <unsplash photo id or URL>   imageAlt: …
// ---
// Markdown body …
import { readFileSync, readdirSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { marked } from 'marked';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const dir = join(root, 'content/posts');
const categories = JSON.parse(readFileSync(join(dir, '_categories.json'), 'utf8'));
const catSlugs = new Set(categories.map((c) => c.slug));
const img = (v) => (/^https?:/.test(v) ? v : `https://images.unsplash.com/photo-${v}?auto=format&fit=crop&w=1200&q=80`);

// FAQ add-ons: content/posts/_faq-*.md, each section "## <slug>" followed by "### Question" + answer.
const faqs = {};
for (const file of readdirSync(dir).filter((f) => /^_faq.*\.md$/.test(f)).sort()) {
  const text = readFileSync(join(dir, file), 'utf8').replace(/\r\n/g, '\n');
  for (const block of text.split(/^## /m).slice(1)) {
    const nl = block.indexOf('\n');
    faqs[block.slice(0, nl).trim()] = block.slice(nl + 1).trim();
  }
}
const withFaq = (slug, body) => {
  if (!faqs[slug]) return body;
  const faq = `## Frequently Asked Questions\n\n${faqs[slug]}`;
  const paras = body.split(/\n{2,}/);
  const last = paras[paras.length - 1];
  // Insert before the closing call-to-action paragraph (one that links to a site page)
  if (/\]\(\/[a-z]/.test(last) && !last.startsWith('#') && !last.startsWith('|')) {
    paras.splice(paras.length - 1, 0, faq);
    return paras.join('\n\n');
  }
  return `${body}\n\n${faq}`;
};

const posts = [];
const problems = [];
for (const file of readdirSync(dir).filter((f) => f.endsWith('.md') && !f.startsWith('_')).sort()) {
  const text = readFileSync(join(dir, file), 'utf8').replace(/\r\n/g, '\n');
  const parts = text.split(/^---\n/m).slice(1); // [front, body, front, body, …]
  for (let i = 0; i < parts.length; i += 2) {
    const meta = {};
    for (const line of parts[i].trim().split('\n')) {
      const m = line.match(/^(\w+):\s*(.*)$/);
      if (m) meta[m[1]] = m[2].trim();
    }
    const body = withFaq(meta.slug, (parts[i + 1] || '').trim());
    const html = marked.parse(body, { gfm: true });
    // Count real words (ignore markdown syntax such as table pipes and heading marks)
    const words = body.replace(/[|#*>`_-]+/g, ' ').split(/\s+/).filter((w) => /[a-z0-9]/i.test(w)).length;
    if (!faqs[meta.slug]) problems.push(`${file} → ${meta.slug}: no FAQ section`);
    const where = `${file} → ${meta.slug || meta.title}`;
    for (const k of ['title', 'slug', 'category', 'keyword', 'metaTitle', 'metaDescription', 'excerpt', 'image', 'imageAlt']) if (!meta[k]) problems.push(`${where}: missing ${k}`);
    if (meta.category && !catSlugs.has(meta.category)) problems.push(`${where}: unknown category ${meta.category}`);
    if (words < 650) problems.push(`${where}: only ${words} words`);
    posts.push({
      title: meta.title, slug: meta.slug, category: meta.category, keyword: meta.keyword,
      metaTitle: meta.metaTitle, metaDescription: meta.metaDescription, excerpt: meta.excerpt,
      tags: (meta.tags || '').split(',').map((t) => t.trim()).filter(Boolean),
      image: img(meta.image || ''), imageAlt: meta.imageAlt, html, words,
    });
  }
}
const dupes = posts.map((p) => p.slug).filter((s, i, a) => a.indexOf(s) !== i);
if (dupes.length) problems.push(`Duplicate slugs: ${dupes.join(', ')}`);

mkdirSync(join(root, 'database/seed'), { recursive: true });
writeFileSync(join(root, 'database/seed/posts.json'), JSON.stringify({ categories, posts }, null, 1));
const total = posts.reduce((n, p) => n + p.words, 0);
console.log(`Imported ${posts.length} posts in ${categories.length} categories · ${total.toLocaleString()} words (avg ${Math.round(total / Math.max(1, posts.length))}).`);
if (problems.length) { console.warn(`\n${problems.length} issue(s):\n  ${problems.join('\n  ')}`); process.exitCode = 1; }
