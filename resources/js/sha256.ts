// Minimal SHA-256 for use when crypto.subtle is unavailable (HTTP dev)
const K = new Uint32Array([
    0x42_8a_2f_98,0x71_37_44_91,0xb5_c0_fb_cf,0xe9_b5_db_a5,0x39_56_c2_5b,0x59_f1_11_f1,0x92_3f_82_a4,0xab_1c_5e_d5,
    0xd8_07_aa_98,0x12_83_5b_01,0x24_31_85_be,0x55_0c_7d_c3,0x72_be_5d_74,0x80_de_b1_fe,0x9b_dc_06_a7,0xc1_9b_f1_74,
    0xe4_9b_69_c1,0xef_be_47_86,0x0f_c1_9d_c6,0x24_0c_a1_cc,0x2d_e9_2c_6f,0x4a_74_84_aa,0x5c_b0_a9_dc,0x76_f9_88_da,
    0x98_3e_51_52,0xa8_31_c6_6d,0xb0_03_27_c8,0xbf_59_7f_c7,0xc6_e0_0b_f3,0xd5_a7_91_47,0x06_ca_63_51,0x14_29_29_67,
    0x27_b7_0a_85,0x2e_1b_21_38,0x4d_2c_6d_fc,0x53_38_0d_13,0x65_0a_73_54,0x76_6a_0a_bb,0x81_c2_c9_2e,0x92_72_2c_85,
    0xa2_bf_e8_a1,0xa8_1a_66_4b,0xc2_4b_8b_70,0xc7_6c_51_a3,0xd1_92_e8_19,0xd6_99_06_24,0xf4_0e_35_85,0x10_6a_a0_70,
    0x19_a4_c1_16,0x1e_37_6c_08,0x27_48_77_4c,0x34_b0_bc_b5,0x39_1c_0c_b3,0x4e_d8_aa_4a,0x5b_9c_ca_4f,0x68_2e_6f_f3,
    0x74_8f_82_ee,0x78_a5_63_6f,0x84_c8_78_14,0x8c_c7_02_08,0x90_be_ff_fa,0xa4_50_6c_eb,0xbe_f9_a3_f7,0xc6_71_78_f2,
]);

function rotr(x: number, n: number): number { return (x >>> n) | (x << (32 - n)); }

export function sha256(input: Uint8Array | string): Uint8Array {
    const message = input instanceof Uint8Array ? input : new TextEncoder().encode(input);
    const length_ = message.length;
    const bitLength = length_ * 8;

    // Pre-processing: pad to 512-bit blocks
    const blocks = Math.ceil((length_ + 9) / 64);
    const padded = new Uint8Array(blocks * 64);
    padded.set(message);
    padded[length_] = 0x80;
    const view = new DataView(padded.buffer);
    view.setUint32(padded.length - 4, bitLength, false);

    let h0 = 0x6a_09_e6_67, h1 = 0xbb_67_ae_85, h2 = 0x3c_6e_f3_72, h3 = 0xa5_4f_f5_3a;
    let h4 = 0x51_0e_52_7f, h5 = 0x9b_05_68_8c, h6 = 0x1f_83_d9_ab, h7 = 0x5b_e0_cd_19;

    const w = new Uint32Array(64);

    for (let offset = 0; offset < padded.length; offset += 64) {
        for (let index = 0; index < 16; index++) {
            w[index] = view.getUint32(offset + index * 4, false);
        }
        for (let index = 16; index < 64; index++) {
            const s0 = rotr(w[index-15], 7) ^ rotr(w[index-15], 18) ^ (w[index-15] >>> 3);
            const s1 = rotr(w[index-2], 17) ^ rotr(w[index-2], 19) ^ (w[index-2] >>> 10);
            // `>>> 0` truncates to unsigned 32-bit; SHA-256 addition is defined mod 2^32.
            w[index] = (w[index-16] + s0 + w[index-7] + s1) >>> 0;
        }

        // SHA-256 working registers a..h (spec naming); `e` widened to `regE` to satisfy the linter.
        let regA = h0, regB = h1, regC = h2, regD = h3, regE = h4, regF = h5, regG = h6, regH = h7;

        for (let index = 0; index < 64; index++) {
            const S1 = rotr(regE, 6) ^ rotr(regE, 11) ^ rotr(regE, 25);
            const ch = (regE & regF) ^ (~regE & regG);
            const t1 = (regH + S1 + ch + K[index] + w[index]) >>> 0;
            const S0 = rotr(regA, 2) ^ rotr(regA, 13) ^ rotr(regA, 22);
            const maj = (regA & regB) ^ (regA & regC) ^ (regB & regC);
            const t2 = (S0 + maj) >>> 0;

            regH = regG; regG = regF; regF = regE; regE = (regD + t1) >>> 0;
            regD = regC; regC = regB; regB = regA; regA = (t1 + t2) >>> 0;
        }

        h0 = (h0 + regA) >>> 0; h1 = (h1 + regB) >>> 0; h2 = (h2 + regC) >>> 0; h3 = (h3 + regD) >>> 0;
        h4 = (h4 + regE) >>> 0; h5 = (h5 + regF) >>> 0; h6 = (h6 + regG) >>> 0; h7 = (h7 + regH) >>> 0;
    }

    const result = new Uint8Array(32);
    const out = new DataView(result.buffer);
    out.setUint32(0, h0); out.setUint32(4, h1); out.setUint32(8, h2); out.setUint32(12, h3);
    out.setUint32(16, h4); out.setUint32(20, h5); out.setUint32(24, h6); out.setUint32(28, h7);
    return result;
}
