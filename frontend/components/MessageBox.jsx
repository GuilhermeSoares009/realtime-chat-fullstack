import { format } from "date-fns"

const MessageBox = ({ message, currentUser }) => {
  return message?.sender?._id !== currentUser._id ? (
    <div className="message-box">
      <img src="https://api.dicebear.com/9.x/adventurer/svg?seed=Jack" alt="profile photo" className="message-profilePhoto" />
      <div className="message-info">
        <p className="text-small-bold">
          {message?.sender?.username} &#160; &#183; &#160; {format(new Date(message?.createdAt), "p")}
        </p>

        {message?.text ? (
          <p className="message-text">{message?.text}</p>
        ) : (
          <img src="https://api.dicebear.com/9.x/adventurer/svg?seed=Jack" alt="message" className="message-photo" />
        )}
      </div>
    </div>
  ) : (
    <div className="message-box justify-end">
      <div className="message-info items-end">
        <p className="text-small-bold">
          {format(new Date(message?.createdAt), "p")}
        </p>

        {message?.text ? (
          <p className="message-text-sender">{message?.text}</p>
        ) : (
          <img src="https://api.dicebear.com/9.x/adventurer/svg?seed=Robert" alt="message" className="message-photo" />
        )}
      </div>
    </div>
  )
}

export default MessageBox