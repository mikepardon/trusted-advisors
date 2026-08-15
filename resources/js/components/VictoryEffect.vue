<template>
  <canvas ref="canvas" class="victory-canvas"></canvas>
</template>

<script setup lang="ts">
import { onBeforeUnmount, useTemplateRef } from "vue";

type EffectType =
  | "gold_rain"
  | "petals"
  | "embers"
  | "confetti"
  | "fireworks"
  | "starfall"
  | "snow";

interface Particle {
  type: EffectType;
  x: number;
  y: number;
  vx: number;
  vy: number;
  size: number;
  color: string;
  rotation: number;
  spin: number;
  sway: number;
  swaySpeed: number;
  life: number;
  decay: number;
}

const canvas = useTemplateRef<HTMLCanvasElement>("canvas");
const particles: Particle[] = [];
const state: { frame: number | undefined; running: boolean } = {
  frame: undefined,
  running: false,
};

const RAINBOW = [
  "#f2c14e",
  "#ffd166",
  "#f78154",
  "#5fad56",
  "#4d9de0",
  "#e15554",
  "#b968c7",
];

const PALETTES: Record<EffectType, string[]> = {
  gold_rain: ["#ffe070", "#f0c050", "#e8b84a", "#ffd166"],
  petals: ["#f7a8c4", "#f9c0d6", "#e88bb0", "#ffd6e6"],
  embers: ["#ff8a3c", "#ff6b2c", "#ffb066", "#e0501c"],
  confetti: RAINBOW,
  fireworks: RAINBOW,
  starfall: ["#ffe070", "#f0c050", "#fff3c0"],
  snow: ["#ffffff", "#e8f0fa", "#dfeaf7"],
};

// Narrow an arbitrary render key to a known effect (unknown => gold rain).
function toEffect(value: string): EffectType {
  switch (value) {
    case "petals":
    case "embers":
    case "confetti":
    case "fireworks":
    case "starfall":
    case "snow": {
      return value;
    }
    default: {
      return "gold_rain";
    }
  }
}

function random(min: number, max: number): number {
  return min + Math.random() * (max - min);
}

function pick(list: string[]): string {
  return list[Math.floor(Math.random() * list.length)];
}


function drawCoin(context: CanvasRenderingContext2D, p: Particle): void {
  const rx = p.size * (0.28 + 0.72 * Math.abs(Math.cos(p.rotation)));
  context.save();
  context.translate(p.x, p.y);
  context.beginPath();
  context.ellipse(0, 0, rx, p.size, 0, 0, Math.PI * 2);
  const gradient = context.createLinearGradient(0, -p.size, 0, p.size);
  gradient.addColorStop(0, "#fff3c0");
  gradient.addColorStop(0.5, p.color);
  gradient.addColorStop(1, "#8a6414");
  context.fillStyle = gradient;
  context.fill();
  context.lineWidth = 1;
  context.strokeStyle = "rgba(90,60,10,0.6)";
  context.stroke();
  context.restore();
}

function drawPetal(context: CanvasRenderingContext2D, p: Particle): void {
  context.save();
  context.translate(p.x, p.y);
  context.rotate(p.rotation);
  context.globalAlpha = Math.min(1, p.life);
  context.beginPath();
  context.ellipse(0, 0, p.size * 0.5, p.size, 0, 0, Math.PI * 2);
  context.fillStyle = p.color;
  context.fill();
  context.restore();
}

function drawGlow(context: CanvasRenderingContext2D, p: Particle): void {
  context.save();
  context.globalCompositeOperation = "lighter";
  const r = p.size * 3;
  const glow = context.createRadialGradient(p.x, p.y, 0, p.x, p.y, r);
  glow.addColorStop(0, p.color);
  glow.addColorStop(1, "rgba(0,0,0,0)");
  context.globalAlpha = Math.max(0, p.life);
  context.fillStyle = glow;
  context.beginPath();
  context.arc(p.x, p.y, r, 0, Math.PI * 2);
  context.fill();
  context.restore();
}

function drawConfetti(context: CanvasRenderingContext2D, p: Particle): void {
  context.save();
  context.translate(p.x, p.y);
  context.rotate(p.rotation);
  context.globalAlpha = Math.max(0, Math.min(1, p.life));
  context.fillStyle = p.color;
  context.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
  context.restore();
}

function drawStar(context: CanvasRenderingContext2D, p: Particle): void {
  context.save();
  context.translate(p.x, p.y);
  context.rotate(p.rotation);
  // Twinkle: alpha oscillates as the star drifts.
  context.globalAlpha = 0.45 + 0.55 * Math.abs(Math.sin(p.sway * 3));
  context.fillStyle = p.color;
  context.font = `${p.size * 2.4}px serif`;
  context.textAlign = "center";
  context.textBaseline = "middle";
  context.fillText("★", 0, 0);
  context.restore();
}

function drawSnow(context: CanvasRenderingContext2D, p: Particle): void {
  context.save();
  context.globalAlpha = 0.85;
  context.fillStyle = p.color;
  context.beginPath();
  context.arc(p.x, p.y, p.size, 0, Math.PI * 2);
  context.fill();
  context.restore();
}

// Advance and draw a single particle for the current frame.
function stepParticle(context: CanvasRenderingContext2D, p: Particle): void {
  switch (p.type) {
    case "gold_rain": {
      p.vy += 0.06;
      p.sway += p.swaySpeed;
      p.x += p.vx + Math.sin(p.sway) * 0.6;
      p.y += p.vy;
      p.rotation += p.spin;
      drawCoin(context, p);
      return;
    }
    case "petals": {
      p.sway += p.swaySpeed;
      p.x += p.vx + Math.sin(p.sway) * 1.3;
      p.y += p.vy;
      p.rotation += p.spin;
      drawPetal(context, p);
      return;
    }
    case "embers": {
      p.vy *= 0.99;
      p.sway += p.swaySpeed;
      p.x += p.vx + Math.sin(p.sway) * 0.5;
      p.y += p.vy;
      p.life -= p.decay;
      p.size *= 0.996;
      drawGlow(context, p);
      return;
    }
    case "confetti": {
      p.vy += 0.35;
      p.vx *= 0.99;
      p.x += p.vx;
      p.y += p.vy;
      p.rotation += p.spin;
      p.life -= p.decay;
      drawConfetti(context, p);
      return;
    }
    case "fireworks": {
      p.vy += 0.08;
      p.vx *= 0.98;
      p.x += p.vx;
      p.y += p.vy;
      p.life -= p.decay;
      drawGlow(context, p);
      return;
    }
    case "starfall": {
      p.sway += p.swaySpeed;
      p.x += p.vx + Math.sin(p.sway) * 0.5;
      p.y += p.vy;
      p.rotation += p.spin;
      drawStar(context, p);
      return;
    }
    default: {
      p.sway += p.swaySpeed;
      p.x += p.vx + Math.sin(p.sway) * 0.8;
      p.y += p.vy;
      drawSnow(context, p);
    }
  }
}

function loop(): void {
  const element = canvas.value;
  const context = element?.getContext("2d");
  if (!element || !context) {
    state.running = false;
    return;
  }

  context.clearRect(0, 0, element.width, element.height);

  for (const p of particles) {
    stepParticle(context, p);
  }

  for (let index = particles.length - 1; index >= 0; index--) {
    const p = particles[index];
    const isGone =
      p.life <= 0 ||
      p.y > (canvas.value?.height ?? 0) + 60 ||
      (p.type === "embers" && p.y < -40);
    if (isGone) {
      particles.splice(index, 1);
    }
  }

  if (particles.length > 0) {
    state.frame = requestAnimationFrame(loop);
  } else {
    context.clearRect(0, 0, element.width, element.height);
    state.running = false;
  }
}

function spawnBurst(
  type: EffectType,
  cx: number,
  cy: number,
  count: number,
  palette: string[],
): void {
  for (let index = 0; index < count; index++) {
    const angle = random(0, Math.PI * 2);
    const speed = random(2, type === "confetti" ? 15 : 7);
    particles.push({
      type,
      x: cx,
      y: cy,
      vx: Math.cos(angle) * speed,
      vy: Math.sin(angle) * speed - (type === "confetti" ? random(2, 7) : 0),
      size: type === "confetti" ? random(6, 13) : random(2, 4),
      color: pick(palette),
      rotation: random(0, Math.PI * 2),
      spin: random(-0.3, 0.3),
      sway: 0,
      swaySpeed: 0,
      life: 1,
      decay: type === "confetti" ? 0.012 : random(0.01, 0.02),
    });
  }
}

/**
 * Play a victory effect by its render key. `count` scales the particle volume
 * for the falling/rising effects (burst effects use their own volume).
 */
function fire(type = "gold_rain", count = 70): void {
  const element = canvas.value;
  if (!element) {
    return;
  }
  element.width = window.innerWidth;
  element.height = window.innerHeight;
  const width = element.width;
  const height = element.height;
  const effect = toEffect(type);
  const palette = PALETTES[effect];

  if (effect === "confetti") {
    spawnBurst(effect, width / 2, height * 0.32, Math.max(count, 120), palette);
  } else if (effect === "fireworks") {
    for (let burst = 0; burst < 6; burst++) {
      spawnBurst(
        effect,
        random(width * 0.2, width * 0.8),
        random(height * 0.14, height * 0.46),
        22,
        palette,
      );
    }
  } else {
    for (let index = 0; index < count; index++) {
      particles.push(fallingParticle(effect, width, height, palette));
    }
  }

  if (!state.running) {
    state.running = true;
    loop();
  }
}

function fallingParticle(
  type: EffectType,
  width: number,
  height: number,
  palette: string[],
): Particle {
  if (type === "gold_rain") {
    return {
      type,
      x: random(0, width),
      y: random(-height * 0.4, 0),
      vx: random(-0.5, 0.5),
      vy: random(2, 5),
      size: random(9, 16),
      color: pick(palette),
      rotation: random(0, Math.PI * 2),
      spin: random(-0.16, 0.16),
      sway: random(0, Math.PI * 2),
      swaySpeed: random(0.01, 0.03),
      life: 1,
      decay: 0,
    };
  }
  if (type === "petals") {
    return {
      type,
      x: random(0, width),
      y: random(-height * 0.4, 0),
      vx: random(-0.3, 0.3),
      vy: random(0.8, 1.9),
      size: random(8, 15),
      color: pick(palette),
      rotation: random(0, Math.PI * 2),
      spin: random(-0.05, 0.05),
      sway: random(0, Math.PI * 2),
      swaySpeed: random(0.02, 0.05),
      life: 1,
      decay: 0,
    };
  }
  if (type === "starfall") {
    return {
      type,
      x: random(0, width),
      y: random(-height * 0.4, 0),
      vx: random(-0.3, 0.3),
      vy: random(1, 2.2),
      size: random(6, 12),
      color: pick(palette),
      rotation: random(0, Math.PI * 2),
      spin: random(-0.03, 0.03),
      sway: random(0, Math.PI * 2),
      swaySpeed: random(0.03, 0.06),
      life: 1,
      decay: 0,
    };
  }
  if (type === "snow") {
    return {
      type,
      x: random(0, width),
      y: random(-height * 0.4, 0),
      vx: random(-0.2, 0.2),
      vy: random(0.6, 1.6),
      size: random(2, 5),
      color: pick(palette),
      rotation: 0,
      spin: 0,
      sway: random(0, Math.PI * 2),
      swaySpeed: random(0.01, 0.03),
      life: 1,
      decay: 0,
    };
  }
  // embers rise from the bottom and fade out
  return {
    type,
    x: random(0, width),
    y: random(height, height + height * 0.2),
    vx: random(-0.4, 0.4),
    vy: random(-2, -4.5),
    size: random(2.5, 5.5),
    color: pick(palette),
    rotation: 0,
    spin: 0,
    sway: random(0, Math.PI * 2),
    swaySpeed: random(0.05, 0.12),
    life: 1,
    decay: random(0.006, 0.014),
  };
}

onBeforeUnmount(() => {
  if (state.frame !== undefined) {
    cancelAnimationFrame(state.frame);
  }
});

defineExpose({ fire });
</script>

<style scoped>
.victory-canvas {
  position: fixed;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 9999;
}
</style>
