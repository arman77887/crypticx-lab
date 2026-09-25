type ToolIntroProps = {
  description: string;
  context?: string;
};

export default function ToolIntro({
  description,
  context,
}: ToolIntroProps) {
  return (
    <section
      className="mt-5 max-w-3xl"
      aria-label="Tool overview"
    >
      <p className="text-base leading-7 text-white/65">
        {description}
      </p>

      {context && (
        <p className="mt-3 text-sm leading-6 text-white/45">
          {context}
        </p>
      )}
    </section>
  );
}
