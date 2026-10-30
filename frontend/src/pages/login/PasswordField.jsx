import { useState } from 'react';
import { Eye, EyeOff, Lock } from 'lucide-react';
import InputField from './InputField.jsx';

/** Password input with a show/hide toggle. */
export default function PasswordField(props) {
  const [visible, setVisible] = useState(false);

  return (
    <InputField
      {...props}
      icon={Lock}
      type={visible ? 'text' : 'password'}
      trailing={(
        <button type="button" className="lg-eye" onClick={() => setVisible(!visible)} aria-label={visible ? 'Hide password' : 'Show password'} aria-pressed={visible}>
          {visible ? <EyeOff aria-hidden="true" /> : <Eye aria-hidden="true" />}
        </button>
      )}
    />
  );
}
