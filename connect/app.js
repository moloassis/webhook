(() => {
  const API = 'proxy.php';

  const steps = {
    1: document.querySelector('[data-step="1"]'),
    2: document.querySelector('[data-step="2"]'),
    3: document.querySelector('[data-step="3"]'),
  };
  const panes = {
    id: document.getElementById('pane-id'),
    scan: document.getElementById('pane-scan'),
    done: document.getElementById('pane-done'),
  };

  const nameInput = document.getElementById('instance-name');
  const btnGenerate = document.getElementById('btn-generate');
  const btnRefresh = document.getElementById('btn-refresh');
  const btnDisconnect = document.getElementById('btn-disconnect');
  const errorEl = document.getElementById('error-id');
  const qrImage = document.getElementById('qr-image');
  const qrLoading = document.getElementById('qr-loading');
  const statusText = document.getElementById('status-text');
  const statusDot = document.getElementById('status-dot');

  let currentInstance = null;
  let pollTimer = null;

  function goToStep(n) {
    Object.entries(steps).forEach(([key, el]) => {
      el.classList.toggle('is-active', Number(key) === n);
      el.classList.toggle('is-done', Number(key) < n);
    });
    Object.values(panes).forEach(p => p.classList.remove('is-active'));
    if (n === 1) panes.id.classList.add('is-active');
    if (n === 2) panes.scan.classList.add('is-active');
    if (n === 3) panes.done.classList.add('is-active');
  }

  function setError(msg) {
    errorEl.textContent = msg;
    errorEl.hidden = !msg;
  }

  async function requestQr(instanceLabel) {
    const res = await fetch(`${API}?action=connect`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ instance: instanceLabel }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Falha ao gerar o QR Code.');
    return data;
  }

  async function checkStatus(instanceName) {
    const res = await fetch(`${API}?action=status&instance=${encodeURIComponent(instanceName)}`);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Falha ao consultar status.');
    return data.state;
  }

  function startPolling(instanceName) {
    stopPolling();
    pollTimer = setInterval(async () => {
      try {
        const state = await checkStatus(instanceName);
        if (state === 'open') {
          stopPolling();
          goToStep(3);
        } else if (state === 'close') {
          statusText.textContent = 'Código expirado — gere um novo.';
          statusDot.style.background = '#C1443B';
        } else {
          statusText.textContent = 'Aguardando leitura…';
        }
      } catch (e) {
        // Silencioso: próxima checagem tenta de novo.
      }
    }, 3000);
  }

  function stopPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
  }

  async function generateQr() {
    const label = nameInput.value.trim();
    setError('');

    if (!label) {
      setError('Digite o nome da empresa ou unidade.');
      return;
    }

    btnGenerate.disabled = true;
    btnGenerate.textContent = 'Gerando…';

    try {
      const { instance, qrcode, alreadyConnected } = await requestQr(label);
      currentInstance = instance;

      if (alreadyConnected) {
        goToStep(3);
        return;
      }

      qrImage.removeAttribute('src');
      qrLoading.style.display = 'flex';
      statusText.textContent = 'Aguardando leitura…';
      statusDot.style.background = '';

      goToStep(2);

      if (qrcode) {
        qrImage.src = qrcode.startsWith('data:') ? qrcode : `data:image/png;base64,${qrcode}`;
        qrLoading.style.display = 'none';
      }

      startPolling(instance);
    } catch (e) {
      setError(e.message);
    } finally {
      btnGenerate.disabled = false;
      btnGenerate.textContent = 'Gerar QR Code';
    }
  }

  btnDisconnect.addEventListener('click', async () => {
    if (!currentInstance) return;
    btnDisconnect.disabled = true;
    btnDisconnect.textContent = 'Desconectando…';
    try {
      const res = await fetch(`${API}?action=disconnect`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ instance: currentInstance }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Falha ao desconectar.');

      stopPolling();
      nameInput.value = '';
      setError('');
      goToStep(1);
    } catch (e) {
      alert(e.message);
    } finally {
      btnDisconnect.disabled = false;
      btnDisconnect.textContent = 'Desconectar';
    }
  });

  btnGenerate.addEventListener('click', generateQr);
  nameInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') generateQr();
  });

  btnRefresh.addEventListener('click', async () => {
    if (!currentInstance) return;
    goToStep(2);
    qrImage.removeAttribute('src');
    qrLoading.style.display = 'flex';
    try {
      const { qrcode } = await requestQr(currentInstance.replace(/^cliente-/, ''));
      if (qrcode) {
        qrImage.src = qrcode.startsWith('data:') ? qrcode : `data:image/png;base64,${qrcode}`;
      }
      qrLoading.style.display = 'none';
      startPolling(currentInstance);
    } catch (e) {
      setError(e.message);
    }
  });
})();
