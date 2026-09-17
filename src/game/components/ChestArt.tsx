export function ChestArt({
  kind,
  large = false,
}: {
  kind: 'bronze' | 'silver' | 'gold';
  large?: boolean;
}) {
  return (
    <div className={`chest-art chest-${kind} ${large ? 'chest-art-large' : ''}`} aria-hidden="true">
      <div className="chest-glint glint-one" />
      <div className="chest-glint glint-two" />
      <div className="chest-lid"><span className="lid-rivet" /></div>
      <div className="chest-body">
        <span className="chest-band band-top" />
        <span className="chest-band band-bottom" />
        <span className="chest-lock"><i /></span>
        <span className="chest-gem gem-left" />
        <span className="chest-gem gem-right" />
      </div>
      <span className="chest-foot foot-left" />
      <span className="chest-foot foot-right" />
    </div>
  );
}
