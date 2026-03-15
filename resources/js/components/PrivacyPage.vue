<template>
  <div class="legal-page">
    <button class="back-btn" @click="$router.back()">&#8592; Back</button>
    <h1 class="legal-title">Privacy Policy</h1>
    <div class="legal-content">
      <p>Last updated: March 2026</p>

      <h2>1. Information We Collect</h2>
      <p>We collect the following information when you use Trusted Advisors:</p>
      <ul>
        <li><strong>Account information:</strong> Name, email address, and profile data provided through Apple or Google sign-in.</li>
        <li><strong>Game data:</strong> Game history, statistics, achievements, and in-game progress.</li>
        <li><strong>Device information:</strong> Device type, operating system, and app version for troubleshooting and compatibility.</li>
        <li><strong>Purchase data:</strong> Transaction records for subscriptions and in-app purchases.</li>
      </ul>

      <h2>2. How We Use Your Information</h2>
      <p>We use your information to:</p>
      <ul>
        <li>Provide and improve the game experience</li>
        <li>Process purchases and manage subscriptions</li>
        <li>Display leaderboards and friend connections</li>
        <li>Send push notifications (with your permission)</li>
        <li>Analyze app usage to improve features</li>
      </ul>

      <h2>3. Data Sharing</h2>
      <p>We do not sell your personal data. We may share limited data with:</p>
      <ul>
        <li><strong>Payment processors:</strong> Stripe, Apple, and Google for transaction processing.</li>
        <li><strong>Analytics services:</strong> Sentry for crash reporting and error tracking.</li>
        <li><strong>Push notification services:</strong> OneSignal for delivering notifications.</li>
      </ul>

      <h2>4. Data Storage and Security</h2>
      <p>Your data is stored securely on our servers. We use industry-standard encryption and security practices to protect your information.</p>

      <h2>5. Your Rights</h2>
      <p>You have the right to:</p>
      <ul>
        <li>Access your personal data</li>
        <li>Request deletion of your account and data</li>
        <li>Opt out of push notifications</li>
        <li>Request a copy of your data</li>
      </ul>

      <h2>6. Children's Privacy</h2>
      <p>Trusted Advisors is not directed at children under 13. We do not knowingly collect personal information from children under 13.</p>

      <h2>7. Changes to This Policy</h2>
      <p>We may update this Privacy Policy from time to time. We will notify you of significant changes through the App.</p>

      <h2>8. Contact</h2>
      <p>For privacy-related questions or data requests, please contact us through the App's support channels.</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PrivacyPage',
};
</script>

<style scoped>
.legal-page {
  max-width: 600px;
  margin: 0 auto;
  padding: 0 16px 100px;
}

.back-btn {
  background: none;
  border: 1px solid rgba(138, 106, 46, 0.4);
  color: var(--text-secondary);
  font-size: 0.85rem;
  padding: 6px 14px;
  cursor: pointer;
  margin-bottom: 8px;
  border-radius: 6px;
  letter-spacing: 0;
}

.back-btn:hover {
  color: var(--text-bright);
  border-color: var(--border-gold);
}

.legal-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 16px;
}

.legal-content h2 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
  margin: 20px 0 8px;
}

.legal-content p,
.legal-content li {
  color: var(--text-secondary);
  font-size: 0.9rem;
  line-height: 1.6;
  margin-bottom: 8px;
}

.legal-content ul {
  padding-left: 20px;
  margin-bottom: 12px;
}
</style>
