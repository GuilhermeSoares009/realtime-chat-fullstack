import { format } from "date-fns"

const MessageBox = ({ message, currentUser }) => {
  const senderId = message?.sender?.id ?? message?.sender?._id ?? message?.sender_id;
  const isMine = senderId === currentUser?.id;
  const text = message?.text ?? message?.content ?? message?.body ?? message?.message;

  if (!isMine) {
    return (
      <div className="message-box">
        <img src={message?.sender?.profileImage || "https://api.dicebear.com/9.x/adventurer/svg?seed=Jack"} alt="profile photo" className="message-profilePhoto" />
        <div className="message-info">
          <p className="text-small-bold">
            {message?.sender?.username || message?.sender?.name} &#160; &#183; &#160; {format(new Date(message?.created_at || message?.createdAt || message?.timestamp), "p")}
          </p>

          <p className="message-text">{text}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="message-box justify-end">
      <div className="message-info items-end">
        <p className="text-small-bold">
          {format(new Date(message?.created_at || message?.createdAt || message?.timestamp), "p")}
        </p>

        <p className="message-text-sender">{text}</p>
      </div>
    </div>
  );
}

export default MessageBox