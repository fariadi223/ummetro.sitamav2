{{--
  Gaya halaman login, disamakan dengan tampilan login SIMARU v2:
  latar gradasi biru-ungu bermotif peta dunia, teks sambutan di kiri atas,
  kartu login di sisi kanan. Dipakai login alumni dan login pengelola.
--}}
<style>
  :root {
    --au-indigo: #6d47cd;
    --au-grad: linear-gradient(90deg, #4775f5 0%, #6a5cf0 50%, #8e4bf3 100%);
    --au-text: #575962;
  }

  body {
    background: #6a5cf0;
  }

  .au-wrap {
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-height: 100dvh;
    padding: 2.5rem 1rem 2rem;
    background: url("{{ asset('assets/img/login/world-map.png') }}") center / cover no-repeat;
    font-family: 'Raleway', 'Public Sans', sans-serif;
  }

  .au-container {
    width: 100%;
    max-width: 1140px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    flex: 1;
  }

  /* ---------- Teks sambutan ---------- */
  .au-welcome {
    margin-bottom: 2rem;
    color: aliceblue;
  }

  .au-welcome h2,
  .au-welcome h1 {
    margin: 0 0 0.375rem;
    color: aliceblue;
    font-weight: 500;
    line-height: 1.35;
  }

  .au-welcome h2 {
    font-size: 1rem;
  }

  .au-welcome h1 {
    font-size: 1.25rem;
  }

  /* ---------- Kartu login ---------- */
  .au-row {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    flex: 1;
  }

  .au-card {
    width: 100%;
    max-width: 440px;
    border-radius: 3px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(30, 20, 90, 0.25);
  }

  .au-card-head {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.25rem 1rem;
    background: var(--au-indigo);
    color: #fff;
    text-align: center;
  }

  .au-card-head img {
    width: 80px;
    height: auto;
  }

  .au-card-head h1 {
    margin: 0.875rem 0 0;
    color: #fff;
    font-size: 1.5rem;
    font-weight: 300;
    letter-spacing: 1px;
  }

  .au-card-body {
    padding: 1.75rem 2.5rem 0.5rem;
    background: #fff;
    color: var(--au-text);
  }

  .au-card-body .form-label {
    color: var(--au-text);
    font-size: 0.875rem;
    text-transform: none;
    letter-spacing: normal;
  }

  .au-field {
    position: relative;
  }

  .au-field .form-control {
    padding-right: 2.75rem;
    height: 2.75rem;
    border-radius: 3px;
    color: var(--au-text);
    background: #fff;
  }

  .au-field .form-select {
    height: 2.75rem;
    border-radius: 3px;
    color: var(--au-text);
    background-color: #fff;
  }

  .au-field .form-control:focus,
  .au-field .form-select:focus {
    border-color: var(--au-indigo);
    box-shadow: 0 0 0 0.15rem rgba(109, 71, 205, 0.15);
  }

  .au-field .au-icon {
    position: absolute;
    top: 0;
    right: 0;
    width: 2.75rem;
    height: 2.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a1a1aa;
    font-size: 1.125rem;
    background: none;
    border: 0;
    padding: 0;
  }

  button.au-icon {
    cursor: pointer;
  }

  .au-card-body .form-check-input:checked {
    background-color: var(--au-indigo);
    border-color: var(--au-indigo);
  }

  .au-btn {
    height: 2.875rem;
    border: 2px solid #fff;
    border-radius: 100px;
    --bs-btn-color: #fff;
    --bs-btn-hover-color: #fff;
    --bs-btn-active-color: #fff;
    background: var(--au-grad);
    color: #fff !important;
    font-weight: 600;
    box-shadow: 0 6px 15px rgba(90, 80, 240, 0.3);
  }

  .au-btn:hover,
  .au-btn:focus {
    background: var(--au-grad);
    color: #fff;
    opacity: 0.9;
  }

  .au-note {
    margin-top: 1rem;
    font-size: 0.8125rem;
    color: #8a8b93;
    text-align: center;
  }

  .au-alert {
    font-size: 0.875rem;
  }

  .au-foot {
    padding: 1.25rem 0;
    margin: 0;
    text-align: center;
    font-size: 0.8125rem;
    color: #8a8b93;
  }

  .au-foot a {
    color: inherit;
  }

  /* ---------- Layar besar ---------- */
  @media (min-width: 768px) {
    .au-wrap {
      padding: 2.5rem 2rem;
    }

    .au-welcome h2 {
      font-size: 1.125rem;
    }

    .au-welcome h1 {
      font-size: 1.5rem;
    }

    .au-card {
      max-width: 460px;
    }
  }

  @media (max-width: 575.98px) {
    .au-welcome {
      text-align: center;
    }

    .au-row {
      justify-content: center;
    }

    .au-card-body {
      padding: 1.5rem 1.25rem 0.25rem;
    }
  }
</style>
