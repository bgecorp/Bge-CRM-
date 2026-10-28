// Small, safe Markdown renderer for assistant replies: paragraphs, headings,
// lists, tables, code blocks, **bold** and `code`. Builds React elements only
// (never innerHTML), so text from the model or the database cannot inject markup.

function inline(text, keyBase) {
  const parts = [];
  const re = /(\*\*[^*]+\*\*|`[^`]+`)/g;
  let last = 0;
  let m;
  let i = 0;
  while ((m = re.exec(text))) {
    if (m.index > last) parts.push(text.slice(last, m.index));
    const tok = m[0];
    parts.push(
      tok.startsWith('**')
        ? <strong key={`${keyBase}-${i++}`}>{tok.slice(2, -2)}</strong>
        : <code key={`${keyBase}-${i++}`}>{tok.slice(1, -1)}</code>,
    );
    last = m.index + tok.length;
  }
  if (last < text.length) parts.push(text.slice(last));
  return parts;
}

const isTableRow = (l) => /^\s*\|.*\|\s*$/.test(l);
const isDivider = (l) => /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(l);
const cells = (l) => l.trim().replace(/^\||\|$/g, '').split('|').map((c) => c.trim());

export default function Markdown({ text }) {
  const lines = text.replace(/\r\n/g, '\n').split('\n');
  const out = [];
  let i = 0;
  let k = 0;

  while (i < lines.length) {
    const line = lines[i];

    if (line.startsWith('```')) {
      const body = [];
      i++;
      while (i < lines.length && !lines[i].startsWith('```')) body.push(lines[i++]);
      i++;
      out.push(<pre key={k++} className="md-pre">{body.join('\n')}</pre>);
      continue;
    }

    if (isTableRow(line) && i + 1 < lines.length && isDivider(lines[i + 1])) {
      const head = cells(line);
      i += 2;
      const rows = [];
      while (i < lines.length && isTableRow(lines[i])) rows.push(cells(lines[i++]));
      out.push(
        <table key={k++} className="table md-table">
          <thead><tr>{head.map((h, j) => <th key={j}>{inline(h, `h${j}`)}</th>)}</tr></thead>
          <tbody>{rows.map((r, ri) => <tr key={ri}>{r.map((c, j) => <td key={j}>{inline(c, `c${ri}-${j}`)}</td>)}</tr>)}</tbody>
        </table>,
      );
      continue;
    }

    const heading = line.match(/^(#{1,4})\s+(.*)$/);
    if (heading) {
      out.push(<div key={k++} className="md-h">{inline(heading[2], `h${k}`)}</div>);
      i++;
      continue;
    }

    if (/^\s*([-*]|\d+\.)\s+/.test(line)) {
      const ordered = /^\s*\d+\./.test(line);
      const items = [];
      while (i < lines.length && /^\s*([-*]|\d+\.)\s+/.test(lines[i])) {
        items.push(lines[i].replace(/^\s*([-*]|\d+\.)\s+/, ''));
        i++;
      }
      const List = ordered ? 'ol' : 'ul';
      out.push(<List key={k++}>{items.map((it, j) => <li key={j}>{inline(it, `li${j}`)}</li>)}</List>);
      continue;
    }

    if (line.trim() === '') {
      i++;
      continue;
    }

    const para = [];
    while (i < lines.length && lines[i].trim() !== '' && !lines[i].startsWith('```') && !/^\s*([-*]|\d+\.)\s+/.test(lines[i]) && !/^#{1,4}\s/.test(lines[i]) && !isTableRow(lines[i])) {
      para.push(lines[i++]);
    }
    out.push(<p key={k++}>{para.flatMap((p, j) => (j ? [<br key={`br${j}`} />, ...inline(p, `p${j}`)] : inline(p, `p${j}`)))}</p>);
  }

  return <div className="md">{out}</div>;
}
