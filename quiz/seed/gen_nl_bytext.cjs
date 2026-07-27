const fs = require("fs");

// 1) Traductions (sortie du workflow) : id -> {q_nl,a_nl,b_nl,c_nl}
const OUT = "C:/Users/enyls/AppData/Local/Temp/claude/c--Users-enyls-Music-Fami-0607/03309ebf-ec4b-4d1c-abf2-634938f29613/tasks/w1l3kr2se.output";
const rawOut = fs.readFileSync(OUT, "utf8");
let dataOut; try { dataOut = JSON.parse(rawOut); } catch (e) { dataOut = JSON.parse(rawOut.slice(rawOut.indexOf("{"))); }
let tr = (dataOut.result || dataOut).translations || [];
if (typeof tr === "string") tr = JSON.parse(tr);
const trById = new Map(tr.map((t) => [t.id, t]));

// 2) Source FR (dump IONOS) : id -> {q,a,b,c}
const sql = fs.readFileSync("C:/Users/enyls/Downloads/db5019880256_hosting-data_io.sql", "utf8");
const re = /INSERT INTO `quiz_questions`[^;]*?VALUES\s*([\s\S]*?);/g;
let m, tuples = [];
while ((m = re.exec(sql))) {
  const body = m[1]; let rows = [], depth = 0, cur = "", inStr = false, esc = false;
  for (let i = 0; i < body.length; i++) { const c = body[i];
    if (inStr) { cur += c; if (esc) esc = false; else if (c === "\\") esc = true; else if (c === "'") inStr = false; }
    else { if (c === "'") { inStr = true; cur += c; } else if (c === "(") { depth++; if (depth === 1) cur = ""; else cur += c; } else if (c === ")") { depth--; if (depth === 0) rows.push(cur); else cur += c; } else if (depth >= 1) cur += c; } }
  tuples.push(...rows);
}
function parseVals(s) { const out = []; let cur = "", inStr = false, esc = false;
  for (let i = 0; i < s.length; i++) { const c = s[i];
    if (inStr) { if (esc) { cur += c; esc = false; } else if (c === "\\") { esc = true; cur += c; } else if (c === "'") inStr = false; else cur += c; }
    else { if (c === "'") inStr = true; else if (c === ",") { out.push(cur); cur = ""; } else if (c === " ") {} else cur += c; } }
  out.push(cur); return out; }
const unesc = (v) => v.replace(/\\'/g, "'").replace(/\\"/g, '"').replace(/\\r/g, "\r").replace(/\\n/g, "\n").replace(/\\\\/g, "\\");
const srcById = new Map();
for (const t of tuples) { const v = parseVals(t); if (v.length < 6) continue; const id = parseInt(v[0], 10); if (!id) continue;
  srcById.set(id, { q: unesc(v[2]), a: unesc(v[3]), b: unesc(v[4]), c: v[5] === "NULL" ? "" : unesc(v[5]) }); }

// uniques du texte FR ? (pour choisir la clé de WHERE)
const counts = new Map();
for (const s of srcById.values()) counts.set(s.q, (counts.get(s.q) || 0) + 1);

function esc(v) { return String(v == null ? "" : v).replace(/\\/g, "\\\\").replace(/'/g, "\\'").replace(/\r/g, "\\r").replace(/\n/g, "\\n"); }

const out = [
  "-- Traductions NL calees sur le TEXTE FR de la question (independant des id).",
  "-- A lancer sur la base Railway. Les colonnes _nl doivent deja exister.",
  "SET NAMES utf8mb4;",
  "START TRANSACTION;",
  "",
];
let n = 0, dup = 0;
for (const [id, s] of srcById) {
  const t = trById.get(id); if (!t) continue;
  const cVal = (s.c && s.c.trim() !== "") ? "'" + esc(t.c_nl) + "'" : "`option_c_nl`";
  // WHERE sur question_text + option_a pour eviter les faux positifs si texte dupliquee
  const whereText = counts.get(s.q) > 1;
  if (whereText) dup++;
  out.push(
    "UPDATE `quiz_questions` SET `question_text_nl`='" + esc(t.q_nl) +
    "', `option_a_nl`='" + esc(t.a_nl) +
    "', `option_b_nl`='" + esc(t.b_nl) +
    "', `option_c_nl`=" + ((s.c && s.c.trim() !== "") ? "'" + esc(t.c_nl) + "'" : "NULL") +
    " WHERE `question_text`='" + esc(s.q) + "'" +
    (whereText ? " AND `option_a`='" + esc(s.a) + "'" : "") + ";"
  );
  n++;
}
out.push("", "COMMIT;", "");
fs.writeFileSync("quiz_questions_nl_BYTEXT.sql", out.join("\n"), "utf8");
console.log("UPDATE (by text):", n, "| textes FR dupliques (WHERE renforce):", dup);
