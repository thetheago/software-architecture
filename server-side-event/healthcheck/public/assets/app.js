/* ============================================================
   Cliente do status page — consome o /stream.php via SSE.
   ============================================================ */

// ---- Tema claro/escuro ----
const themeToggle = document.getElementById("theme-toggle");
themeToggle?.addEventListener("click", () => {
  const html = document.documentElement;
  html.dataset.theme = html.dataset.theme === "dark" ? "light" : "dark";
});

// ---- Referencias de DOM ----
const els = {
  overall:     document.getElementById("overall"),
  overallTitle: document.querySelector("#overall .banner__title"),
  overallIcon: document.querySelector("#overall .banner__icon"),
  liveBadge:   document.getElementById("live-badge"),
  liveLabel:   document.getElementById("live-label"),
  lastUpdated: document.getElementById("last-updated"),
  list:        document.getElementById("components-list"),
  feed:        document.getElementById("event-feed"),
  rowTemplate: document.getElementById("component-row"),
};

// Icone do banner por status.
const OVERALL_ICON = {
  operational: "✓",
  maintenance: "⚙",
  degraded:    "!",
  partial:     "!",
  major:       "✕",
};

/** Atualiza o badge de conexao (bolinha "ao vivo"). */
function setLiveState(state, label) {
  els.liveBadge.dataset.state = state;
  els.liveLabel.textContent = label;
}

function stamp() {
  els.lastUpdated.textContent = new Date().toLocaleTimeString();
  els.lastUpdated.dateTime = new Date().toISOString();
}

// ============================================================
//  Conexao SSE
// ============================================================
const source = new EventSource("/stream.php");

source.onopen = () => setLiveState("open", "ao vivo");
source.onerror = () => setLiveState("closed", "reconectando…");

// Estado inicial: reconstroi a lista inteira a partir do snapshot.
source.addEventListener("snapshot", (e) => {
  const { components } = JSON.parse(e.data);
  els.list.innerHTML = "";
  components.forEach(renderComponent);
  stamp();
});

// Atualiza (ou cria) um unico componente.
source.addEventListener("component", (e) => {
  renderComponent(JSON.parse(e.data));
  stamp();
});

// Atualiza o banner geral.
source.addEventListener("overall", (e) => {
  renderOverall(JSON.parse(e.data));
  stamp();
});

// Novo item no feed de eventos.
source.addEventListener("incident", (e) => {
  pushEvent(JSON.parse(e.data));
});

// ============================================================
//  Render
// ============================================================

/** Atualiza o banner geral (cor via data-status, titulo e icone). */
function renderOverall(data) {
  els.overall.dataset.status = data.status;
  els.overallTitle.textContent = data.title;
  els.overallIcon.textContent = OVERALL_ICON[data.status] ?? "•";
}

/** Cria ou atualiza a linha de um componente (identificado por data-id). */
function renderComponent(data) {
  let row = els.list.querySelector(`.component[data-id="${data.id}"]`);

  if (!row) {
    row = els.rowTemplate.content.firstElementChild.cloneNode(true);
    row.dataset.id = data.id;
    els.list.appendChild(row);
  }

  row.dataset.status = data.status;
  row.querySelector(".component__name").textContent = data.name;
  row.querySelector(".component__uptime").textContent =
    data.status === "maintenance" ? "—" : `${data.uptime}%`;
  row.querySelector(".component__state-label").textContent = data.label;

  // Pisca rapidinho para sinalizar a atualizacao.
  row.classList.remove("component--flash");
  void row.offsetWidth; // reinicia a animacao
  row.classList.add("component--flash");
}

/** Adiciona um item no topo do feed de eventos ao vivo. */
function pushEvent(data) {
  els.feed.querySelector(".event-feed__empty")?.remove();

  const li = document.createElement("li");
  li.dataset.status = data.status;
  li.innerHTML = `
    <span class="event-feed__pill"></span>
    <span>${data.message}</span>
    <time>${data.time}</time>`;
  els.feed.prepend(li);

  // Mantem no maximo 12 itens no feed.
  while (els.feed.children.length > 12) {
    els.feed.lastElementChild.remove();
  }
}
