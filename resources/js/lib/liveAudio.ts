const WORKLET_CODE = `
class PCMProcessor extends AudioWorkletProcessor {
  constructor(options) {
    super();
    this.sourceRate = options.processorOptions.sampleRate || 48000;
    this.targetRate = 16000;
    this.buffer = new Float32Array(4096);
    this.bufferIdx = 0;
  }
  process(inputs) {
    const input = inputs[0];
    if (!input || !input.length) return true;
    const channel = input[0];
    const ratio = this.sourceRate / this.targetRate;
    const outputSamples = Math.floor(channel.length / ratio);

    for (let i = 0; i < outputSamples; i++) {
      const start = Math.floor(i * ratio);
      const end = Math.floor((i + 1) * ratio);
      let sum = 0;
      let count = 0;
      for (let j = start; j < end && j < channel.length; j++) {
        sum += channel[j];
        count++;
      }
      const sample = count > 0 ? sum / count : 0;
      if (this.bufferIdx < this.buffer.length) this.buffer[this.bufferIdx++] = sample;
      else {
        this.flush();
        this.buffer[this.bufferIdx++] = sample;
      }
    }

    if (this.bufferIdx >= 512) this.flush();
    return true;
  }
  flush() {
    if (this.bufferIdx === 0) return;
    const pcmData = new Int16Array(this.bufferIdx);
    for (let i = 0; i < this.bufferIdx; i++) {
      let s = Math.max(-1, Math.min(1, this.buffer[i]));
      pcmData[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
    }
    this.port.postMessage(pcmData);
    this.bufferIdx = 0;
  }
}
registerProcessor('pcm-processor', PCMProcessor);
`;

export interface MicCaptureHandle {
  context: AudioContext;
  stop: () => void;
}

/**
 * Capture mic PCM at 16 kHz without routing the raw mic signal to speakers.
 * The worklet must connect to a silent sink so the graph runs reliably.
 */
export async function startMicCapture(
  stream: MediaStream,
  onPcm: (pcm: Int16Array) => void,
  onLevel?: (level: number) => void,
): Promise<MicCaptureHandle> {
  const context = new AudioContext();
  const source = context.createMediaStreamSource(stream);

  const blob = new Blob([WORKLET_CODE], { type: 'application/javascript' });
  const url = URL.createObjectURL(blob);

  try {
    await context.audioWorklet.addModule(url);
  } finally {
    URL.revokeObjectURL(url);
  }

  const workletNode = new AudioWorkletNode(context, 'pcm-processor', {
    processorOptions: { sampleRate: context.sampleRate },
  });

  // Silent sink — keeps the capture graph alive without monitoring mic on speakers.
  const silentGain = context.createGain();
  silentGain.gain.value = 0;

  workletNode.port.onmessage = (event: MessageEvent<Int16Array>) => {
    const pcmData = event.data;
    onPcm(pcmData);

    if (onLevel) {
      let sum = 0;
      for (let i = 0; i < pcmData.length; i++) {
        const sample = pcmData[i] / 32768;
        sum += sample * sample;
      }
      onLevel(Math.sqrt(sum / pcmData.length));
    }
  };

  source.connect(workletNode);
  workletNode.connect(silentGain);
  silentGain.connect(context.destination);

  if (context.state === 'suspended') {
    await context.resume();
  }

  return {
    context,
    stop: () => {
      try {
        source.disconnect();
      } catch {
        // Ignore teardown errors.
      }
      try {
        workletNode.disconnect();
      } catch {
        // Ignore teardown errors.
      }
      try {
        silentGain.disconnect();
      } catch {
        // Ignore teardown errors.
      }
      void context.close().catch(() => {});
    },
  };
}

export class LivePcmPlayer {
  private context: AudioContext;

  private nextPlayTime = 0;

  private activeSources = 0;

  private readonly outputGain: GainNode;

  constructor(private readonly onIdle?: () => void) {
    this.context = new AudioContext({ sampleRate: 24000 });
    this.outputGain = this.context.createGain();
    this.outputGain.gain.value = 1;
    this.outputGain.connect(this.context.destination);
  }

  isPlaying(): boolean {
    return this.activeSources > 0;
  }

  async ensureRunning(): Promise<void> {
    if (this.context.state === 'suspended') {
      await this.context.resume();
    }

    if (this.nextPlayTime === 0) {
      this.nextPlayTime = this.context.currentTime + 0.05;
    }
  }

  enqueue(pcm: Int16Array): void {
    void this.playChunk(pcm);
  }

  clear(): void {
    this.nextPlayTime = 0;
  }

  stop(): void {
    this.clear();
    void this.context.close().catch(() => {});
  }

  private async playChunk(pcm: Int16Array): Promise<void> {
    await this.ensureRunning();

    const float32Data = new Float32Array(pcm.length);
    for (let i = 0; i < pcm.length; i++) {
      float32Data[i] = pcm[i] / 32768;
    }

    const buffer = this.context.createBuffer(1, float32Data.length, 24000);
    buffer.getChannelData(0).set(float32Data);

    const source = this.context.createBufferSource();
    source.buffer = buffer;
    source.connect(this.outputGain);

    const now = this.context.currentTime;
    if (this.nextPlayTime < now + 0.02) {
      this.nextPlayTime = now + 0.02;
    }

    source.start(this.nextPlayTime);
    this.nextPlayTime += buffer.duration;
    this.activeSources += 1;

    source.onended = () => {
      this.activeSources -= 1;
      if (this.activeSources === 0) {
        this.onIdle?.();
      }
    };
  }
}
