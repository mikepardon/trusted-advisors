<template>
  <div class="auth-screen">
    <div class="banner-wrap">
      <img src="/images/banner.png" alt="Trusted Advisors" class="banner-img" />
    </div>

    <div class="card-panel auth-panel">
      <p class="auth-desc">Sign in to begin your adventure</p>

      <button class="btn-primary signin-btn" @click="signIn">
        Sign In with MPGames
      </button>
    </div>
  </div>
</template>

<script>
import { useAuth } from '../stores/auth';

export default {
  name: 'LoginRegister',
  setup() {
    const auth = useAuth();
    return { auth };
  },
  methods: {
    signIn() {
      this.auth.startLogin();
    },
  },
};
</script>

<style scoped>
.auth-screen {
  max-width: 440px;
  margin: 0 auto;
}

.banner-wrap {
  text-align: center;
  margin-bottom: 25px;
}

.banner-img {
  max-width: 100%;
  height: auto;
}

.auth-panel {
  padding: 32px 24px;
  text-align: center;
}

.auth-desc {
  color: var(--text-secondary);
  font-size: 1rem;
  margin-bottom: 24px;
  font-style: italic;
}

.signin-btn {
  font-size: 1.1rem;
  padding: 14px 32px;
  width: 100%;
}

@media (max-width: 768px) {
  .auth-panel {
    padding: 24px 18px;
  }

  .signin-btn {
    font-size: 1rem;
  }
}
</style>
